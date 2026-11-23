import { expect, test } from '@playwright/test';

/**
 * CP-NF-02 (RNF-03): el botón "Listo" mide al menos 72, 56 y 44 px de alto según el nivel.
 * Se mide en el navegador real (jsdom no calcula CSS) sobre la galería de desarrollo.
 */
const MINIMO = { Exploradoras: 72, Aventureras: 56, Pro: 44 } as const;

test('CP-NF-02 · objetivos táctiles por nivel', async ({ page }) => {
  await page.goto('/app/galeria');
  for (const [nivel, minimo] of Object.entries(MINIMO)) {
    await page.getByRole('radiogroup', { name: 'Nivel de experiencia' }).getByRole('radio', { name: nivel }).click();
    await expect(page.locator('html')).toHaveAttribute('data-nivel', nivel.toLowerCase());
    const listo = page.getByRole('button', { name: /listo/i }).first();
    await listo.scrollIntoViewIfNeeded();
    const caja = await listo.boundingBox();
    expect(caja?.height ?? 0, `${nivel}: alto del botón Listo`).toBeGreaterThanOrEqual(minimo);
    // Las teclas del PIN también son objetivos táctiles principales.
    const tecla = await page.getByRole('button', { name: '5', exact: true }).first().boundingBox();
    expect(tecla?.height ?? 0, `${nivel}: alto de una tecla del PIN`).toBeGreaterThanOrEqual(minimo);
  }
});
