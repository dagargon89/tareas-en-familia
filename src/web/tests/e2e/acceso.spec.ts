import { expect, test } from '@playwright/test';

/**
 * CP-009 (RF-01): la hija elige su avatar, escribe su PIN y llega a "Mi día" con su nombre.
 * Usa las credenciales de prueba de DemoSeeder (solo existen en desarrollo y pruebas).
 */
const PADRE = { email: 'demo@tareas.local', password: 'FamiliaDemo2026' };
const LUNA = { apodo: 'Luna', pin: '3917' };

test('CP-009 · Luna entra con su PIN y llega a Mi día', async ({ page }) => {
  await page.goto('/app/');

  // Sin sesión de dispositivo, la app lleva al login del padre.
  await expect(page).toHaveURL(/\/app\/login$/);
  await page.getByLabel('Correo').fill(PADRE.email);
  await page.getByLabel('Contraseña').fill(PADRE.password);
  await page.getByRole('button', { name: 'Entrar' }).click();

  // Selector de perfiles.
  await expect(page.getByRole('heading', { name: '¿Quién eres?' })).toBeVisible();
  await page.getByRole('button', { name: LUNA.apodo, exact: true }).click();

  // PIN con el tamaño de su nivel.
  await expect(page.getByText(`Hola, ${LUNA.apodo}. Escribe tu PIN`)).toBeVisible();
  for (const d of LUNA.pin) {
    await page.getByRole('button', { name: d, exact: true }).click();
  }

  // "Mi día" con su nombre y la navegación de Exploradoras (3 botones).
  await expect(page).toHaveURL(/\/app\/hoy$/);
  await expect(page.getByRole('banner').getByText(LUNA.apodo)).toBeVisible();
  await expect(page.getByRole('navigation', { name: 'Secciones' }).getByRole('link')).toHaveCount(3);
  await expect(page.locator('html')).toHaveAttribute('data-nivel', 'exploradoras');

  // Cambiar de perfil vuelve al selector sin cerrar la sesión del dispositivo.
  await page.getByRole('button', { name: 'Cambiar de perfil' }).click();
  await expect(page.getByRole('heading', { name: '¿Quién eres?' })).toBeVisible();
});
