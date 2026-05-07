const { chromium } = require('playwright');

// ─────────────────────────────────────────────────────────────────────────────
// Script: subir-gratis.js
// Objetivo: login → browse_listings.php → encontrar el primer anuncio que:
//   1. Tenga el botón "Subir gratis" activo (span.accion-subir-gratis > a)
//   2. Su teléfono coincida con el pasado por parámetro
// Luego navega al enlace renewad.php?id=... y confirma si fue exitoso.
// ─────────────────────────────────────────────────────────────────────────────

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

  const username  = payload.username;
  const password  = payload.password;
  const telefono  = String(payload.telefono || '').trim();
  const headless  = payload.headless !== false;
  const timeoutMs = payload.timeoutMs || 30000;

  if (!username || !password || !telefono) {
    console.log(JSON.stringify({ ok: false, error: 'Faltan username, password o telefono' }));
    process.exit(1);
  }

  let browser;
  let page;

  const result = {
    ok: false,
    loginOk: false,
    listingFound: false,
    listingId: null,
    renewUrl: null,
    renewOk: false,
    currentUrl: null,
    error: null
  };

  try {
    browser = await chromium.launch({ headless });

    const context = await browser.newContext({
      viewport: { width: 1366, height: 900 }
    });

    page = await context.newPage();
    page.setDefaultTimeout(timeoutMs);

    // ── Login ────────────────────────────────────────────────────────────────
    const loginUrl = 'https://www.destacamos.net/login.php?loc=browse_listings.php';
    await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#username');
    await page.waitForSelector('#password');
    await page.fill('#username', username);
    await page.fill('#password', password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('networkidle');

    result.currentUrl = page.url();
    result.loginOk = await page.locator('text=Salir').first().isVisible().catch(() => false);

    if (!result.loginOk) {
      throw new Error('Login no confirmado. Verifica usuario/contraseña.');
    }

    // ── Ir al listado de anuncios ─────────────────────────────────────────────
    const listUrl = 'https://www.destacamos.net/browse_listings.php';

    // Si el login ya redirige a browse_listings, no navegamos de nuevo
    if (!page.url().includes('browse_listings')) {
      await page.goto(listUrl, { waitUntil: 'domcontentloaded' });
      await page.waitForLoadState('networkidle');
    }

    result.currentUrl = page.url();

    // ── Buscar el anuncio candidato ───────────────────────────────────────────
    //
    // Estrategia:
    //   1. Recorremos cada <tr id="pXXXXX"> del listado
    //   2. Dentro del tr, buscamos <strong> que contenga el teléfono
    //   3. Y que también tenga span.accion-subir-gratis > a (botón activo)
    //   4. Al primero que cumpla ambas condiciones, extraemos el href del enlace
    //      y navegamos a él.
    //
    // Usamos evaluate para hacer toda la búsqueda en una sola llamada al DOM
    // (más rápido que iterar locators desde Node).

    const renewHref = await page.evaluate((tel) => {
      // Todas las filas de anuncios tienen id que empieza por "p" seguido de dígitos
      const rows = Array.from(document.querySelectorAll('tr[id^="p"]'));

      for (const row of rows) {
        // ¿Tiene el teléfono buscado?
        const strongs = Array.from(row.querySelectorAll('td strong'));
        const hasPhone = strongs.some(el => el.textContent.trim() === tel);
        if (!hasPhone) continue;

        // ¿Tiene el botón "Subir gratis" activo (= con enlace <a> dentro)?
        const subirLink = row.querySelector('span.accion-subir-gratis > a');
        if (!subirLink) continue;

        // Candidato encontrado: devolvemos el href absoluto
        return subirLink.href;
      }

      return null; // No encontrado
    }, telefono);

    if (!renewHref) {
      result.error = `No se encontró ningún anuncio con teléfono "${telefono}" y "Subir gratis" activo`;
      console.log(JSON.stringify(result));
      await browser.close();
      process.exit(0); // Salida limpia, no es un error de script sino de negocio
    }

    // Extraer el listingId del href (renewad.php?id=XXXX&...)
    try {
      const urlObj = new URL(renewHref);
      result.listingId = urlObj.searchParams.get('id');
    } catch (_) {
      result.listingId = renewHref; // fallback
    }

    result.listingFound = true;
    result.renewUrl = renewHref;

    // ── Navegar al enlace de "Subir gratis" ──────────────────────────────────
    await page.goto(renewHref, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    result.currentUrl = page.url();

    // Comprobamos si la renovación fue exitosa.
    // Habitualmente la web muestra un mensaje de confirmación o redirige.
    // Buscamos señales positivas comunes:
    const successSignals = [
      page.locator('text=subido').first().isVisible().catch(() => false),
      page.locator('text=renovado').first().isVisible().catch(() => false),
      page.locator('text=actualizado').first().isVisible().catch(() => false),
      page.locator('text=éxito').first().isVisible().catch(() => false),
      page.locator('text=correctamente').first().isVisible().catch(() => false),
    ];

    const checks = await Promise.all(successSignals);
    result.renewOk = checks.some(Boolean);

    // Si no hay mensaje explícito, asumimos OK si la URL cambió respecto al renewUrl
    // (la web suele redirigir tras procesar)
    if (!result.renewOk && result.currentUrl !== renewHref) {
      result.renewOk = true;
      result.renewNote = 'Redirigido tras renovar (sin mensaje explícito detectado)';
    }

    result.ok = result.renewOk;

    console.log(JSON.stringify(result));
    await browser.close();
    process.exit(0);

  } catch (e) {
    result.ok = false;
    result.error = e.message;
    result.currentUrl = page ? page.url() : null;

    try { if (browser) await browser.close(); } catch (_) {}

    console.log(JSON.stringify(result));
    process.exit(1);
  }
}

main();