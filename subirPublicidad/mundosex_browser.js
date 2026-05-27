#!/usr/bin/env node
/**
 * mundosex_browser.js — Automatización con Playwright para mundosexanuncio.com
 *
 * Ejecuta Chrome headless, hace login, rellena formulario, sube fotos y guarda.
 *
 * Uso: node subirPublicidad/mundosex_browser.js '<json>'
 * Payload: { username, password, listingId, fields:{title,description,phone,email,whatsapp,provincia,ciudad}, photos:[...] }
 * Output:  { ok, error, loginOk, saveClicked, saveResult, photosProcessed, warnings }
 */

const { chromium } = require('playwright');

const SITE = 'https://www.mundosexanuncio.com';
const TIMEOUT = 60000;

function log(...args) { console.error('[mundosex]', ...args); }
function warn(msg) { return '⚠ ' + msg; }

async function main() {
  const args = process.argv.slice(2);
  if (args.length < 1) {
    console.log(JSON.stringify({ ok: false, error: 'Falta payload (--file=ruta o JSON directo)' }));
    process.exit(1);
  }

  let payload;
  // Soporta --file=ruta para evitar credenciales en línea de comandos
  const fileArg = args.find(a => a.startsWith('--file='));
  if (fileArg) {
    const fs = require('fs');
    const filePath = fileArg.substring('--file='.length);
    try {
      payload = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    } catch (e) {
      console.log(JSON.stringify({ ok: false, error: 'No se pudo leer el fichero de payload: ' + e.message }));
      process.exit(1);
    }
  } else {
    try { payload = JSON.parse(args[0]); } catch (e) {
      console.log(JSON.stringify({ ok: false, error: 'JSON inválido: ' + e.message }));
      process.exit(1);
    }
  }

  const username = (payload.username || '').trim();
  const password = (payload.password || '').trim();
  const listingId = (payload.listingId || '').trim();
  const fields = payload.fields || {};
  const photos = (payload.photos || []).filter(p => p && String(p).trim());

  const result = {
    ok: false, error: '', loginOk: false, saveClicked: false,
    saveResult: null, photosProcessed: 0, warnings: [],
  };

  if (!username || !password || !listingId) {
    result.error = 'Faltan username, password o listingId';
    console.log(JSON.stringify(result));
    process.exit(1);
  }

  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });

  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    viewport: { width: 1366, height: 768 },
    locale: 'es-ES',
  });

  const page = await context.newPage();
  page.setDefaultTimeout(TIMEOUT);

  // Debug: capturar errores y logs del navegador
  page.on('console', msg => {
    if (msg.type() === 'error') log('BROWSER-ERROR:', msg.text());
  });
  page.on('pageerror', err => log('PAGE-ERROR:', err.message));

  try {
    // ═══════════════════════════════════════════
    // STEP 1: Home + legal gate
    // ═══════════════════════════════════════════
    log('Navegando a la home...');
    await page.goto(SITE + '/', { waitUntil: 'domcontentloaded', timeout: 30000 });

    // Accept legal gate
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        const gate = page.locator('#alegal');
        if (await gate.isVisible({ timeout: 4000 }).catch(() => false)) {
          log('Aceptando gate legal...');
          await gate.locator('input[type="button"]').click();
          await page.waitForTimeout(2000);
        } else break;
      } catch (e) { break; }
    }

    // ═══════════════════════════════════════════
    // STEP 2: Login
    // ═══════════════════════════════════════════
    log('Navegando a /misAnuncios...');
    await page.goto(SITE + '/misAnuncios', { waitUntil: 'domcontentloaded', timeout: 30000 });

    // Legal gate again
    try {
      const gate2 = page.locator('#alegal');
      if (await gate2.isVisible({ timeout: 3000 }).catch(() => false)) {
        await gate2.locator('input[type="button"]').click();
        await page.waitForTimeout(2000);
      }
    } catch (e) {}

    // Login
    const alreadyLogged = await page.locator('text=Estás conectado').isVisible({ timeout: 3000 }).catch(() => false);
    if (!alreadyLogged) {
      await page.waitForSelector('#usuarioLogin', { timeout: 15000 });
      log('Rellenando login...');
      await page.locator('#email').fill(username);
      await page.locator('#password').fill(password);
      await page.waitForTimeout(600);

      // Click submit and wait for navigation/redirect
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {}),
        page.locator('#usuarioLogin input[type="submit"]').click(),
      ]);
      await page.waitForTimeout(3000);

      const loggedIn = await page.locator('text=Estás conectado').isVisible({ timeout: 10000 }).catch(() => false);
      if (loggedIn) {
        result.loginOk = true;
        log('Login OK');
      } else {
        const errText = await page.locator('#men').textContent().catch(() => '');
        result.error = 'Login fallido: ' + (errText || 'No se detectó sesión');
        console.log(JSON.stringify(result));
        await browser.close();
        process.exit(1);
      }
    } else {
      result.loginOk = true;
      log('Sesión ya activa');
    }

    // ═══════════════════════════════════════════
    // STEP 3: Edit page
    // ═══════════════════════════════════════════
    const editUrl = SITE + '/publicar/editar/' + listingId;
    log('Navegando a: ' + editUrl);
    await page.goto(editUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });

    // Wait for form
    await page.waitForSelector('#form_pub', { timeout: 15000 }).catch(() => {});
    const formVisible = await page.locator('#form_pub').isVisible({ timeout: 5000 }).catch(() => false);

    if (!formVisible) {
      const rateText = await page.locator('text=editado recientemente').isVisible().catch(() => false);
      result.error = rateText ? 'Rate-limit: anuncio editado recientemente' : 'Formulario #form_pub no encontrado';
      console.log(JSON.stringify(result));
      await browser.close();
      process.exit(1);
    }
    log('Formulario cargado, esperando inicialización JS...');
    // Esperar a que Cloudflare Rocket Loader + Prototype + TinyMCE terminen de cargar
    await page.waitForTimeout(3000);
    // Cloudflare Rocket Loader: el onsubmit del form y los onchange de los select
    // están guardados por window.__cfRLUnblockHandlers. Hay que esperar a que
    // Rocket Loader los desbloquee, o el formulario NO se enviará al hacer submit.
    try {
      await page.waitForFunction(() => !window.__cfRLUnblockHandlers, { timeout: 25000 });
      log('Rocket Loader finalizado');
    } catch (e) {
      log('Timeout esperando Rocket Loader — continuando igualmente');
      result.warnings.push(warn('Rocket Loader no finalizó a tiempo'));
    }
    await page.waitForTimeout(2000);

    // ═══════════════════════════════════════════
    // STEP 4: Fill fields
    // ═══════════════════════════════════════════
    let touched = 0;

    const setField = async (selector, value, label) => {
      try {
        const el = page.locator(selector);
        if (await el.isVisible({ timeout: 2000 }).catch(() => false)) {
          if (await el.evaluate(e => e.tagName === 'SELECT')) {
            await el.selectOption({ label: String(value) });
          } else {
            await el.fill(String(value));
          }
          touched++;
        } else {
          result.warnings.push(warn(label + ' no visible: ' + selector));
        }
      } catch (e) {
        result.warnings.push(warn('Error en ' + label + ': ' + e.message));
      }
    };

    if (fields.provincia) await setField('#id_provincia', fields.provincia, 'provincia');
    if (fields.ciudad) {
      // El selector de provincia dispara selProvList() que rellena el dropdown de ciudad.
      // Con Cloudflare Rocket Loader, el onchange puede tardar en ejecutarse.
      await page.waitForTimeout(3000);
      // Esperar a que el dropdown de ciudad tenga opciones reales (más allá del option "0")
      try {
        await page.waitForFunction(() => {
          const sel = document.getElementById('id_ciudad');
          return sel && sel.options && sel.options.length > 1;
        }, { timeout: 10000 });
      } catch (e) {
        result.warnings.push(warn('Timeout esperando opciones de ciudad'));
      }
      await page.waitForTimeout(500);
      await setField('#id_ciudad', fields.ciudad, 'ciudad');
    }
    if (fields.title)       await setField('#titol', fields.title, 'título');
    if (fields.phone)       await setField('#telefono', String(fields.phone), 'teléfono');
    if (fields.email)       await setField('#mail', fields.email, 'email');

    // Description (TinyMCE — puede ser iframe o textarea)
    if (fields.description) {
      try {
        // Intentar textarea directo primero
        const ta = page.locator('#descripcio');
        if (await ta.isVisible({ timeout: 2000 }).catch(() => false)) {
          await ta.fill(fields.description);
          touched++;
        } else {
          // TinyMCE iframe
          const frame = page.frameLocator('#descripcio_ifr');
          const body = frame.locator('#tinymce');
          await body.fill(fields.description);
          touched++;
        }
      } catch (e) {
        result.warnings.push(warn('Error en descripción: ' + e.message));
      }
    }

    // WhatsApp checkbox
    if (fields.whatsapp !== undefined) {
      try {
        const cb = page.locator('#whatsapp');
        if (await cb.isVisible({ timeout: 2000 }).catch(() => false)) {
          if (fields.whatsapp && !(await cb.isChecked())) await cb.check();
          else if (!fields.whatsapp && await cb.isChecked()) await cb.uncheck();
          touched++;
        }
      } catch (e) {}
    }

    // Conditions checkbox (required)
    try {
      await page.locator('#condiciones').check({ force: true, timeout: 3000 });
    } catch (e) {
      result.warnings.push(warn('No se pudo marcar condiciones'));
    }

    log('Campos rellenados: ' + touched);

    // ═══════════════════════════════════════════
    // STEP 5: Delete existing photos
    // ═══════════════════════════════════════════
    if (photos.length > 0) {
      log('Eliminando fotos existentes...');
      // Los botones de eliminar son input[type="button"] con id="remove_1", etc.
      // Al hacer clic, ejecutan remove_img() / remove_img_edit() que marcan
      // los campos hidden image[del][X] = 1 mediante JavaScript.
      let removed = 0;
      // Marcar hidden fields directamente como respaldo
      for (const delId of ['image_del_1', 'image_del_2', 'image_del_3', 'image_del_4']) {
        try {
          const hidden = page.locator('#' + delId);
          if (await hidden.isVisible({ timeout: 1000 }).catch(() => false) || await hidden.count() > 0) {
            await hidden.evaluate(el => { el.value = '1'; });
            removed++;
          }
        } catch (e) {}
      }
      // También hacer clic en los botones de eliminar (dispara la lógica JS del sitio)
      const removeBtns = page.locator('input[type="button"][id^="remove_"]');
      const count = await removeBtns.count();
      for (let i = 0; i < count; i++) {
        try {
          await removeBtns.nth(i).click({ force: true, timeout: 3000 });
          removed++;
          await page.waitForTimeout(500);
        } catch (e) {
          result.warnings.push(warn('No se pudo hacer clic en remove_' + i + ': ' + e.message));
        }
      }
      log('Fotos marcadas para eliminar: ' + removed + ' (hidden: OK, buttons: ' + count + ')');
      await page.waitForTimeout(1000);
    }

    // ═══════════════════════════════════════════
    // STEP 6: Upload new photos
    // ═══════════════════════════════════════════
    let photosOk = 0;
    if (photos.length > 0) {
      log('Subiendo ' + photos.length + ' fotos...');

      // Usar fileChooser cuando sea posible (más fiable que setInputFiles para inputs ocultos)
      // Los inputs file pueden estar ocultos por CSS en el widget de subida
      const fileSlots = [
        { sel: '#image_tpl',        fallback: '[name="image_tpl"]' },
        { sel: '#image_1',          fallback: '[name="image_1"]' },
        { sel: '#image_2',          fallback: '[name="image_2"]' },
        { sel: '#image_3',          fallback: '[name="image_3"]' },
        { sel: '#image_4',          fallback: '[name="image_4"]' },
        { sel: '#image_5',          fallback: '[name="image_5"]' },
      ];

      for (let i = 0; i < Math.min(photos.length, fileSlots.length); i++) {
        const photoPath = photos[i];
        const slot = fileSlots[i];
        try {
          const input = page.locator(slot.sel);
          // Usar force:true porque el input puede estar oculto (CSS)
          await input.setInputFiles(photoPath, { timeout: 5000 });
          photosOk++;
          log('  Foto ' + (i+1) + ' → ' + slot.sel);
          await page.waitForTimeout(1000);
        } catch (e1) {
          // Fallback: probar con el selector de nombre
          try {
            const input2 = page.locator(slot.fallback);
            await input2.setInputFiles(photoPath, { timeout: 3000 });
            photosOk++;
            log('  Foto ' + (i+1) + ' → ' + slot.fallback + ' (fallback)');
            await page.waitForTimeout(1000);
          } catch (e2) {
            // Último intento: fileChooser via click en el label/container
            try {
              // Hacer clic en el área del widget de subida para abrir el diálogo
              const uploadArea = page.locator('#image_tpl, [name="image_tpl"]').locator('..');
              const [fileChooser] = await Promise.all([
                page.waitForEvent('filechooser', { timeout: 5000 }),
                uploadArea.click({ force: true }),
              ]);
              await fileChooser.setFiles(photoPath);
              photosOk++;
              log('  Foto ' + (i+1) + ' → fileChooser');
            } catch (e3) {
              result.warnings.push(warn('No se pudo subir foto ' + (i+1) + ': ' + e3.message));
            }
          }
        }
      }
    }

    result.photosProcessed = photosOk;
    log('Fotos subidas: ' + photosOk + '/' + photos.length);

    // ═══════════════════════════════════════════
    // STEP 7: Save — escuchar respuesta ANTES de hacer clic
    // ═══════════════════════════════════════════
    log('Clicando Guardar cambios...');

    // Verificar Rocket Loader antes de submit (el onsubmit tiene guard)
    try {
      const rlReady = await page.evaluate(() => !window.__cfRLUnblockHandlers);
      if (!rlReady) {
        log('Rocket Loader aún activo antes de save, esperando...');
        await page.waitForFunction(() => !window.__cfRLUnblockHandlers, { timeout: 15000 }).catch(() => {});
      }
    } catch (e) {}

    // El formulario usa target="pubresfrm" (iframe) — no hay navegación de página.
    // Capturamos la respuesta de red del POST al iframe.
    let capturedBody = null;
    const responseHandler = async (response) => {
      const url = response.url();
      if (url.includes('/publicar/insertar/' + listingId) && response.status() === 200) {
        try {
          capturedBody = await response.text();
          log('Respuesta capturada: ' + capturedBody.substring(0, 300));
        } catch (e) {}
      }
    };
    page.on('response', responseHandler);

    // También esperar posible navegación (por si el servidor redirige la página principal)
    const navPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null);

    // Hacer clic en el botón de guardar
    await page.locator('#sendButton').click({ timeout: 10000 });
    result.saveClicked = true;

    // Esperar a que la respuesta del iframe llegue
    await page.waitForTimeout(6000);

    // Quitar listener
    page.off('response', responseHandler);

    // Esperar navegación si ocurrió
    await navPromise;
    await page.waitForTimeout(2000);

    if (capturedBody) {
      try {
        let json = JSON.parse(capturedBody);
        result.saveResult = json;
        log('Respuesta JSON: ' + JSON.stringify(json).substring(0, 200));

        if (json.estado === 1) {
          result.ok = true;
          log('✓ Save confirmado (estado=1)');
        } else {
          result.error = json.mensaje || 'Servidor rechazó el guardado (estado=' + (json.estado ?? '?') + ')';
        }
      } catch (e) {
        // Puede no ser JSON; si contiene "estado":1 igual es válido
        if (capturedBody.includes('"estado":1') || capturedBody.includes('"estado": 1')) {
          result.ok = true;
          log('✓ Save confirmado (JSON parcial con estado=1)');
        } else {
          result.error = 'Error parseando respuesta: ' + e.message + ' | body=' + capturedBody.substring(0, 200);
        }
      }
    } else {
      // No se capturó la respuesta de red → chequeo visual
      log('Respuesta de red no capturada, chequeo visual...');

      const okVis1 = await page.locator('text=anuncio ha sido modificado').isVisible({ timeout: 3000 }).catch(() => false);
      const okVis2 = await page.locator('.flash_ok').isVisible({ timeout: 3000 }).catch(() => false);
      const okVis3 = page.url().includes('/confirmacion/');

      if (okVis1 || okVis2 || okVis3) {
        result.ok = true;
        log('✓ Confirmación visual detectada');
      } else {
        // Buscar errores visibles antes de asumir éxito
        const flashKo = await page.locator('.flash_ko').textContent({ timeout: 2000 }).catch(() => '');
        const menErr = await page.locator('#men').textContent({ timeout: 2000 }).catch(() => '');
        const anyError = flashKo || menErr || '';

        if (anyError && anyError.includes('editado recientemente')) {
          result.error = 'Rate-limit: anuncio editado recientemente';
        } else if (anyError && anyError.trim()) {
          result.error = anyError.trim();
        } else if (page.url().includes('/misAnuncios') || page.url().includes('/privado/')) {
          // Redirigido al panel de usuario → probablemente éxito
          result.ok = true;
          result.warnings.push('Redirigido a panel (asumido éxito)');
        } else if (page.url().includes('/publicar/editar/')) {
          // Seguimos en la página de edición → el submit probablemente falló
          // Comprobar si el botón está disabled (se deshabilita al hacer submit exitoso)
          const btnDisabled = await page.locator('#sendButton').isDisabled({ timeout: 2000 }).catch(() => false);
          if (btnDisabled) {
            // El botón está disabled → el JS de submit se ejecutó (probablemente éxito)
            result.ok = true;
            result.warnings.push('Botón disabled pero sin confirmación explícita');
          } else {
            result.error = 'El formulario no se envió (posible error de validación o Rocket Loader). Revisa los campos: provincia, ciudad, título (min 10 chars, 5 letras), teléfono, condiciones.';
          }
        } else {
          result.error = 'Redirigido inesperadamente a: ' + page.url();
        }
      }
    }

    // ═══════════════════════════════════════════
    // STEP 8: Output
    // ═══════════════════════════════════════════
    console.log(JSON.stringify(result));

  } catch (error) {
    result.error = 'Error fatal: ' + error.message;
    console.log(JSON.stringify(result));
  } finally {
    await browser.close();
  }
}

main();
