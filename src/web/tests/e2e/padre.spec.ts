import { expect, test } from '@playwright/test';
import type { Categoria, Dia, Instancia, Progreso, Tarea } from '../../src/api/ApiClient.ts';
import { HIJAS, PADRE, abrirSesion, api, entrarComo, esperarCarga, modoPadre } from './ayuda.ts';

/**
 * CP-087 (RF-16): el padre entra a modo padre con su PIN, aprueba 2 tareas desde la bandeja
 * y la hija ve su XP actualizado en "Mis logros". Contra la API real (base `tareas_dev`).
 * Prepara sus propias tareas (títulos únicos), así que se puede repetir sin resembrar.
 */
test('CP-087 · El padre aprueba 2 tareas y Sol ve su XP en Mis logros', async ({ page }) => {
  const { request } = page;
  const sello = Date.now().toString(36);
  const titulos = [`Regar ${sello}`, `Guardar ropa ${sello}`];

  // Preparación por la API: dos tareas de hoy para Sol, marcadas hechas por ella.
  await abrirSesion(request);
  await modoPadre(request);
  const casa = (await api<Categoria[]>(request, 'GET', '/api/v1/admin/categorias')).find((c) => c.nombre === 'Casa');
  const hoy = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Ciudad_Juarez' }).format(new Date());
  for (const [i, titulo] of titulos.entries()) {
    await api<Tarea>(request, 'POST', '/api/v1/admin/tareas', {
      perfil_id: HIJAS.sol.id, categoria_id: casa?.id, titulo, icono: 'planta', puntos: 6 + i, tipo: 'casa', requiere_foto: false,
      recurrencia: { frecuencia: 'una_vez', dias_semana: null, hora: null, fecha_inicio: hoy, fecha_fin: null },
    });
  }
  await api(request, 'DELETE', '/api/v1/modo-padre');
  await entrarComo(request, HIJAS.sol);
  const dia = await api<Dia>(request, 'GET', '/api/v1/yo/dia');
  const mias = dia.instancias.filter((i: Instancia) => titulos.includes(i.titulo));
  expect(mias).toHaveLength(2);
  for (const i of mias) await api(request, 'POST', `/api/v1/yo/instancias/${i.id}/hecha`);
  const antes = (await api<Progreso>(request, 'GET', '/api/v1/yo/progreso')).xp;

  // El padre entra a modo padre con su PIN en la pantalla.
  await page.goto('/app/modo-padre');
  await expect(page.getByText('Modo padre · escribe tu PIN')).toBeVisible();
  for (const d of PADRE.pin) await page.getByRole('button', { name: d, exact: true }).click();
  await expect(page).toHaveURL(/\/app\/padre$/);

  // Aprueba las dos desde la bandeja.
  await page.goto('/app/padre/aprobaciones');
  await esperarCarga(page);
  for (const titulo of titulos) {
    const item = page.getByRole('listitem').filter({ hasText: titulo });
    await item.getByRole('button', { name: 'Aprobar' }).click();
    await expect(item).toHaveCount(0);
  }

  // Sol ve su XP nuevo en "Mis logros": los 13 puntos de las tareas (más insignias, si ganó alguna).
  const despues = (await api<Progreso>(request, 'GET', '/api/v1/yo/progreso')).xp;
  expect(despues).toBeGreaterThanOrEqual(antes + 13);
  await page.goto('/app/logros');
  await esperarCarga(page);
  await expect(page.getByRole('heading', { name: 'Mis logros' })).toBeVisible();
  await expect(page.getByText(`${despues} XP`, { exact: true })).toBeVisible();
});
