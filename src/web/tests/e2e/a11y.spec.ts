import AxeBuilder from '@axe-core/playwright';
import { expect, test, type BrowserContext, type BrowserContextOptions, type Page } from '@playwright/test';
import { HIJAS, abrirSesion, api, entrarComo, esperarCarga, modoPadre } from './ayuda.ts';

/**
 * CP-NF-03 (RNF-04): axe en las pantallas principales de los 3 niveles y del padre,
 * con 0 violaciones serias o críticas (WCAG 2.2 A y AA). Una sola sesión por viewport
 * para no chocar con el límite de 5 logins por minuto.
 */
test.describe.configure({ mode: 'serial' });

let contexto: BrowserContext;
let page: Page;

test.beforeAll(async ({ browser }, info) => {
  // Hereda baseURL, viewport, idioma y zona horaria del proyecto (tablet o celular).
  contexto = await browser.newContext(info.project.use as BrowserContextOptions);
  page = await contexto.newPage();
});

test.afterAll(async () => {
  await contexto.close();
});

async function revisar(ruta: string) {
  await page.goto(ruta);
  await esperarCarga(page);
  const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  const graves = r.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(graves.map((v) => `${v.id}: ${v.help} (${v.nodes.length}) → ${v.nodes[0]?.target.join(' ')}`), `en ${ruta}`).toEqual([]);
}

test('login, selector y PIN', async () => {
  await revisar('/app/login');
  await abrirSesion(page.request);
  await revisar('/app/');
  await revisar(`/app/entrar/${HIJAS.luna.id}`);
});

for (const hija of Object.values(HIJAS)) {
  test(`pantallas de ${hija.apodo} (${hija.nivel})`, async () => {
    await entrarComo(page.request, hija);
    for (const ruta of ['/app/hoy', '/app/premios', '/app/logros']) await revisar(ruta);
    if (hija.nivel !== 'exploradoras') {
      await revisar('/app/calendario');
      await revisar('/app/mis-tareas/nueva');
    }
  });
}

// Modo oscuro (solo Aventureras y Pro, doc 08): mismo axe, incluido el contraste de color.
test('pantallas en modo oscuro de Sol y Estrella', async () => {
  for (const hija of [HIJAS.sol, HIJAS.estrella]) {
    await entrarComo(page.request, hija);
    await api(page.request, 'PATCH', '/api/v1/yo/preferencias', { tema: 'oscuro' });
    try {
      for (const ruta of ['/app/hoy', '/app/premios', '/app/logros', '/app/calendario', '/app/mis-tareas/nueva']) {
        await revisar(ruta);
        await expect(page.locator('html')).toHaveAttribute('data-tema', 'oscuro');
      }
    } finally {
      await api(page.request, 'PATCH', '/api/v1/yo/preferencias', { tema: 'claro' });
    }
  }
});

test('pantallas del padre', async () => {
  await modoPadre(page.request);
  for (const ruta of ['/app/padre', '/app/padre/aprobaciones', '/app/padre/tareas', '/app/padre/semana', '/app/padre/perfiles', '/app/padre/premios', '/app/padre/metas', '/app/padre/ajustes']) {
    await revisar(ruta);
  }
});
