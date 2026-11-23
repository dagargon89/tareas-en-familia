import { expect, test } from '@playwright/test';
import { HIJAS, abrirSesion, entrarComo } from '../ayuda.ts';

/**
 * Pruebas sobre el build de producción servido por `vite preview` (:4173) con proxy a la API.
 * Una sola sesión para todo el archivo (límite de 5 logins por minuto).
 */
test.describe.configure({ mode: 'serial' });

/**
 * Perfil "4G" de Chrome DevTools: 9 Mbps de bajada, 1.5 Mbps de subida y 60 ms de latencia,
 * con CPU 4× más lenta (celular de gama media). `vite preview` no comprime; LiteSpeed sí
 * (gzip/brotli), así que en el hosting el resultado será mejor que aquí.
 */
const RED_4G = { offline: false, latency: 60, downloadThroughput: (9 * 1024 * 1024) / 8, uploadThroughput: (1.5 * 1024 * 1024) / 8 };
const LCP_MAXIMO_MS = 1500;

test('CP-NF-01 y CP-SEG-09 · Mi día carga en menos de 1.5 s en 4G y sin terceros', async ({ browser }, info) => {
  const contexto = await browser.newContext({ ...info.project.use, serviceWorkers: 'block' });
  const page = await contexto.newPage();
  await abrirSesion(page.request);
  await entrarComo(page.request, HIJAS.sol);

  // Caché vacía y red/CPU limitadas.
  const cdp = await contexto.newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
  await cdp.send('Network.emulateNetworkConditions', RED_4G);
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  const origenes = new Set<string>();
  page.on('request', (r) => origenes.add(new URL(r.url()).origin));

  await page.goto('/app/hoy', { waitUntil: 'networkidle' });
  await expect(page.getByRole('heading', { name: /¡Hola, Sol!/ })).toBeVisible();

  const lcp = await page.evaluate(
    () =>
      new Promise<number>((resolve) => {
        new PerformanceObserver((lista) => {
          const entradas = lista.getEntries();
          resolve(entradas[entradas.length - 1].startTime);
        }).observe({ type: 'largest-contentful-paint', buffered: true });
      }),
  );
  test.info().annotations.push({ type: 'LCP', description: `${Math.round(lcp)} ms` });
  console.log(`LCP de Mi día (4G, CPU 4×, caché vacía): ${Math.round(lcp)} ms`);

  // CP-SEG-09: ninguna petición fuera del propio origen (RNF-06).
  expect([...origenes]).toEqual([new URL(info.project.use.baseURL ?? '').origin]);
  expect(lcp).toBeLessThan(LCP_MAXIMO_MS);
  await contexto.close();
});
