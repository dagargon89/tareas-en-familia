import type { APIRequestContext, Page } from '@playwright/test';

/** Credenciales de prueba de DemoSeeder (solo existen en desarrollo y pruebas). */
export const PADRE = { email: 'demo@tareas.local', password: 'FamiliaDemo2026', pin: '4826' };
export const HIJAS = {
  luna: { id: 1, apodo: 'Luna', pin: '3917', nivel: 'exploradoras' },
  sol: { id: 2, apodo: 'Sol', pin: '5203', nivel: 'aventureras' },
  estrella: { id: 3, apodo: 'Estrella', pin: '8462', nivel: 'pro' },
} as const;

/**
 * Abre la sesión del dispositivo por la API (con CSRF), sin pasar por la pantalla de login.
 * Comparte cookies con la página. El login tiene límite de 5 por minuto por IP: cada archivo
 * de pruebas debe iniciar sesión pocas veces.
 */
export async function abrirSesion(request: APIRequestContext): Promise<void> {
  let token = ((await (await request.get('/api/v1/csrf')).json()) as { token: string }).token;
  const r = await request.post('/api/v1/auth/login', { headers: { 'X-CSRF-TOKEN': token }, data: { email: PADRE.email, password: PADRE.password } });
  if (!r.ok()) throw new Error(`login falló: ${r.status()} ${await r.text()}`);
  token = r.headers()['x-csrf-token'] ?? token;
  csrfActual = token;
}

let csrfActual = '';

async function post(request: APIRequestContext, ruta: string, data?: unknown) {
  return api(request, 'POST', ruta, data);
}

/**
 * Petición a la API con el CSRF vigente (lo renueva con el de cada respuesta). Falla si no es 2xx.
 * Sirve para preparar datos en los e2e sin pasar por la UI.
 */
export async function api<T = unknown>(request: APIRequestContext, metodo: 'GET' | 'POST' | 'PUT' | 'DELETE', ruta: string, data?: unknown): Promise<T> {
  const r = await request.fetch(ruta, { method: metodo, headers: { 'X-CSRF-TOKEN': csrfActual }, data });
  csrfActual = r.headers()['x-csrf-token'] ?? csrfActual;
  if (!r.ok()) throw new Error(`${metodo} ${ruta} falló: ${r.status()} ${await r.text()}`);
  return (r.status() === 204 ? undefined : await r.json()) as T;
}

/** Entra al perfil de una hija por la API. */
export async function entrarComo(request: APIRequestContext, hija: (typeof HIJAS)[keyof typeof HIJAS]): Promise<void> {
  await post(request, `/api/v1/perfiles/${hija.id}/entrar`, { pin: hija.pin });
}

/** Activa modo padre por la API. */
export async function modoPadre(request: APIRequestContext): Promise<void> {
  await post(request, '/api/v1/modo-padre', { pin: PADRE.pin });
}

/** Espera a que la pantalla termine de cargar (sin indicador "Cargando…"). */
export async function esperarCarga(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle');
  await page.getByRole('status').filter({ hasText: 'Cargando' }).waitFor({ state: 'detached' }).catch(() => undefined);
}
