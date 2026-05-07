const { chromium } = require('playwright');
const path = require('path');

function clampNumber(value, fallback) {
  const n = Number(value);
  return Number.isFinite(n) ? n : fallback;
}

function randomBetween(min, max) {
  min = clampNumber(min, 0);
  max = clampNumber(max, min);
  if (max <= min) return min;
  return min + Math.random() * (max - min);
}

async function humanPause(page, minMs, maxMs) {
  const waitMs = Math.round(randomBetween(minMs, maxMs));
  await page.waitForTimeout(waitMs);
  return waitMs;
}

async function typeHuman(locator, value, minDelay = 60, maxDelay = 140) {
  const text = String(value);
  await locator.click({ clickCount: 3 }).catch(() => {});
  await locator.fill('');
  await locator.type(text, { delay: Math.round(randomBetween(minDelay, maxDelay)) });
}

// ─────────────────────────────────────────────
// Helpers campo edición texto / select / checkbox
// ─────────────────────────────────────────────

async function setTextIfProvided(page, selector, value, touched, key, humanize = {}) {
  if (value === undefined) return;
  const locator = page.locator(selector);
  const count = await locator.count();
  if (!count) throw new Error(`No se encontró el campo ${key} con selector ${selector}`);
  const fieldMin = clampNumber(humanize.fieldDelayMinMs, 120);
  const fieldMax = clampNumber(humanize.fieldDelayMaxMs, 320);
  await humanPause(page, 120, 320);
  await typeHuman(locator.first(), String(value), fieldMin, fieldMax);
  touched[key] = value;
}

async function setSelectIfProvided(page, selector, value, touched, key, humanize = {}) {
  if (value === undefined) return;
  const locator = page.locator(selector);
  const count = await locator.count();
  if (!count) throw new Error(`No se encontró el select ${key} con selector ${selector}`);
  await humanPause(page, 120, 280);
  await locator.first().selectOption({ label: String(value) }).catch(async () => {
    await locator.first().selectOption(String(value));
  });
  touched[key] = value;
}

async function setCheckboxIfProvided(page, selector, value, touched, key, humanize = {}) {
  if (value === undefined) return;
  const locator = page.locator(selector);
  const count = await locator.count();
  if (!count) throw new Error(`No se encontró el checkbox ${key} con selector ${selector}`);
  const desired = Boolean(value);
  const current = await locator.first().isChecked();
  if (desired !== current) {
    await humanPause(page, 120, 260);
    if (desired) await locator.first().check();
    else await locator.first().uncheck();
  }
  touched[key] = desired;
}

async function setCheckboxGroupIfProvided(page, selector, values, touched, key, humanize = {}) {
  if (values === undefined) return;
  if (!Array.isArray(values)) throw new Error(`El campo ${key} debe ser un array`);
  const desired = new Set(values.map(v => String(v)));
  const items = page.locator(selector);
  const count = await items.count();
  if (!count) throw new Error(`No se encontraron checkboxes para ${key} con selector ${selector}`);
  for (let i = 0; i < count; i++) {
    const item = items.nth(i);
    const value = await item.getAttribute('value');
    const shouldBeChecked = desired.has(String(value));
    const isChecked = await item.isChecked();
    if (shouldBeChecked && !isChecked) { await humanPause(page, 80, 180); await item.check(); }
    else if (!shouldBeChecked && isChecked) { await humanPause(page, 80, 180); await item.uncheck(); }
  }
  touched[key] = values;
}

// ─────────────────────────────────────────────
// Fase de edición de fotos
// ─────────────────────────────────────────────

async function editPhotosPhase(page, listingId, photoPaths, result, humanize = {}) {
  const photosUrl = `https://www.destacamos.net/edit_photos.php?id=${encodeURIComponent(listingId)}`;

  await page.goto(photosUrl, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle');

  // Esperar a que el uploader inicialice (crea el DOM de fotos existentes)
  await page.waitForTimeout(2500);

  result.photosPageOk = await page.locator('#file-uploader').count() > 0;
  if (!result.photosPageOk) {
    throw new Error('No se encontró el contenedor #file-uploader en la página de fotos');
  }

  // ── 1. Borrar todas las fotos existentes ──────────────────────────────────
  let deletedCount = 0;
  const MAX_DELETE_LOOPS = 10; // seguro contra bucle infinito

  for (let loop = 0; loop < MAX_DELETE_LOOPS; loop++) {
    // Los botones de borrar tienen la clase "delete" y están dentro de los <li>
    const deleteButtons = page.locator('.qq-upload-photo-list li .delete');
    const count = await deleteButtons.count();
    if (count === 0) break;

    // Interceptar el diálogo de confirmación si aparece (algunos sitios lo usan)
    page.once('dialog', async dialog => {
      await dialog.accept();
    });

    // Clicar el primero y esperar a que desaparezca del DOM
    const firstBtn = deleteButtons.first();
    const liLocator = firstBtn.locator('xpath=ancestor::li[1]');

    await firstBtn.click();

    // Esperamos hasta 5 s a que el <li> desaparezca (el JS lo elimina tras la respuesta AJAX)
    await liLocator.waitFor({ state: 'detached', timeout: 5000 }).catch(() => {
      // Si no desaparece solo, continuamos igualmente
    });

    await humanPause(page, 500, 1400); // pequeño margen entre borrados
    deletedCount++;
  }

  result.photosDeleted = deletedCount;

  // ── 2. Subir las nuevas fotos ─────────────────────────────────────────────
  result.photosUploaded = 0;

  if (!photoPaths || photoPaths.length === 0) {
    result.photosUploadSkipped = true;
    return;
  }

  // Validar rutas antes de intentar subir
  const fs = require('fs');
  for (const p of photoPaths) {
    if (!fs.existsSync(p)) {
      throw new Error(`No se encontró el archivo de foto: ${p}`);
    }
  }

  // qq.FileUploader inyecta un <input type="file"> oculto dentro de .qq-uploader
  // Playwright puede interactuar con él directamente con setInputFiles
  const fileInput = page.locator('.qq-uploader input[type="file"]');
  const inputCount = await fileInput.count();
  if (!inputCount) {
    throw new Error('No se encontró el input de archivo del uploader');
  }

  // El uploader puede tener multiple=false, así que subimos de una en una
  // para garantizar compatibilidad y esperar la respuesta AJAX de cada una
  for (const photoPath of photoPaths) {
    const absPath = path.resolve(photoPath);

    await humanPause(page, clampNumber(humanize.photoDelayMinMs, 1800), clampNumber(humanize.photoDelayMaxMs, 4200));
    await fileInput.setInputFiles(absPath);

    // Esperamos un tiempo prudencial por la subida
    await humanPause(page, clampNumber(humanize.photoDelayMinMs, 1800), clampNumber(humanize.photoDelayMaxMs, 4200));

    // Verificar que apareció una nueva foto en la lista
    const liCount = await page.locator('.qq-upload-photo-list li').count();
    result.photosUploaded++;
    result.photoListAfterUpload = liCount;
  }

  // Pequeña pausa final para que el servidor procese todo
  await humanPause(page, 800, 1800);
}

// ─────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────

async function main() {
  const rawArg = process.argv[2];
  if (!rawArg) {
    console.log(JSON.stringify({ ok: false, error: 'Falta payload JSON' }));
    process.exit(1);
  }

  let payload;
  try {
    payload = JSON.parse(rawArg);
  } catch (e) {
    console.log(JSON.stringify({ ok: false, error: 'Payload JSON inválido', detail: e.message }));
    process.exit(1);
  }

  const username   = payload.username;
  const password   = payload.password;
  const listingId  = payload.listingId;
  const headless   = payload.headless !== false;
  const timeoutMs  = payload.timeoutMs || 30000;
  const save       = payload.save === true;
  const fields     = payload.fields || {};
  const editPhotos = payload.editPhotos === true;   // ← activar fase fotos
  const photoPaths = payload.photos || [];           // ← rutas locales de las nuevas fotos
  const humanize = payload.humanize || {};

  if (!username || !password || !listingId) {
    console.log(JSON.stringify({ ok: false, error: 'Faltan username, password o listingId' }));
    process.exit(1);
  }

  let browser;
  let page;

  try {
    browser = await chromium.launch({ headless });

    const context = await browser.newContext({
      viewport: { width: 1366, height: 900 }
    });

    page = await context.newPage();
    page.setDefaultTimeout(timeoutMs);

    const result = {
      ok: true,
      loginOk: false,
      editPageOk: false,
      saveAttempted: save,
      saveClicked: false,
      touchedFields: {},
      photosPageOk: false,
      photosDeleted: 0,
      photosUploaded: 0,
      currentUrl: null
    };

    // ── Login ────────────────────────────────────────────────────────────────
    const loginUrl = 'https://www.destacamos.net/login.php?loc=browse_listings.php';
    await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#username');
    await page.waitForSelector('#password');
    await typeHuman(page.locator('#username'), username, 50, 120);
    await humanPause(page, 180, 450);
    await typeHuman(page.locator('#password'), password, 50, 120);
    await humanPause(page, 250, 650);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('networkidle');

    result.currentUrl = page.url();
    result.loginOk = await page.locator('text=Salir').first().isVisible().catch(() => false);

    if (!result.loginOk) {
      throw new Error('Login no confirmado');
    }

    // ── Fase edición de campos (si hay fields definidos) ─────────────────────
    const hasFields = Object.keys(fields).length > 0;

    if (hasFields) {
      const editUrl = `https://www.destacamos.net/editad.php?id=${encodeURIComponent(listingId)}`;
      await page.goto(editUrl, { waitUntil: 'domcontentloaded' });
      await page.waitForLoadState('networkidle');

      result.currentUrl = page.url();

      const formExists = await page.locator('#formularioeditar').count() > 0;
      const saveExists = await page.locator('#guardarcambios').count() > 0;
      result.editPageOk = formExists && saveExists;

      if (!result.editPageOk) {
        throw new Error('No se encontró la página/formulario de edición');
      }

      await setTextIfProvided(page, '#title',        fields.title,       result.touchedFields, 'title', humanize);
      await setTextIfProvided(page, '#description',  fields.description, result.touchedFields, 'description', humanize);
      await setTextIfProvided(page, '#nombre',        fields.nombre,      result.touchedFields, 'nombre', humanize);
      await setTextIfProvided(page, '#telefono',      fields.telefono,    result.touchedFields, 'telefono', humanize);
      await setTextIfProvided(page, '#city',          fields.city,        result.touchedFields, 'city', humanize);
      await setTextIfProvided(page, '#zip',           fields.zip,         result.touchedFields, 'zip', humanize);

      await setCheckboxIfProvided(page, '#whatsapp', fields.whatsapp, result.touchedFields, 'whatsapp', humanize);
      await setCheckboxIfProvided(page, '#telegram', fields.telegram, result.touchedFields, 'telegram', humanize);

      await setSelectIfProvided(page, '#localidad',         fields.localidad,          result.touchedFields, 'localidad', humanize);
      await setSelectIfProvided(page, '#edad',              fields.edad,               result.touchedFields, 'edad', humanize);
      await setSelectIfProvided(page, '#horario_de_trabajo',fields.horario_de_trabajo, result.touchedFields, 'horario_de_trabajo', humanize);
      await setSelectIfProvided(page, '#color_de_pelo',     fields.color_de_pelo,      result.touchedFields, 'color_de_pelo', humanize);
      await setSelectIfProvided(page, '#altura',            fields.altura,             result.touchedFields, 'altura', humanize);
      await setSelectIfProvided(page, '#peso',              fields.peso,               result.touchedFields, 'peso', humanize);
      await setSelectIfProvided(page, '#profesion',         fields.profesion,          result.touchedFields, 'profesion', humanize);
      await setSelectIfProvided(page, '#pais_de_origen',    fields.pais_de_origen,     result.touchedFields, 'pais_de_origen', humanize);

      await setCheckboxGroupIfProvided(page, 'input[name="idiomas[]"]',     fields.idiomas,      result.touchedFields, 'idiomas', humanize);
      await setCheckboxGroupIfProvided(page, 'input[name="dias_trabajo[]"]', fields.dias_trabajo, result.touchedFields, 'dias_trabajo', humanize);

      if (save) {
        await humanPause(page, 400, 900);
        await page.click('#guardarcambios');
        result.saveClicked = true;
        await page.waitForLoadState('networkidle').catch(() => {});
        await humanPause(page, clampNumber(humanize.postSaveDelayMinSec, 3) * 1000, clampNumber(humanize.postSaveDelayMaxSec, 8) * 1000);
        result.currentUrl = page.url();
      }
    }

    // ── Fase edición de fotos ─────────────────────────────────────────────────
    if (editPhotos) {
      await editPhotosPhase(page, listingId, photoPaths, result, humanize);
      result.currentUrl = page.url();
    }

    console.log(JSON.stringify(result));
    await browser.close();
    process.exit(0);

  } catch (e) {
    const errorResult = {
      ok: false,
      error: e.message,
      currentUrl: page ? page.url() : null
    };
    try { if (browser) await browser.close(); } catch (_) {}
    console.log(JSON.stringify(errorResult));
    process.exit(1);
  }
}

main();