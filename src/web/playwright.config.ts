import { existsSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';
import { defineConfig, devices } from '@playwright/test';

// En WSL sin sudo, las bibliotecas de Chromium (libnss3, libasound) vienen de Homebrew y se
// enlazan en esta carpeta. Si no existe (CI, otra máquina), no se toca el entorno del navegador.
const libsLocales = join(homedir(), '.local/lib/playwright-libs');
const launchOptions = existsSync(libsLocales)
  ? { env: { ...process.env, LD_LIBRARY_PATH: [libsLocales, process.env.LD_LIBRARY_PATH].filter(Boolean).join(':') } }
  : {};

/**
 * E2E contra la API real (doc 06 §1): Vite en :5173 con proxy a CodeIgniter en :8080.
 * Requiere la base `tareas_dev` sembrada con CatalogoSeeder y DemoSeeder.
 * Dos viewports del RNF-05: tablet 768×1024 y celular 360×780.
 */
export default defineConfig({
  testDir: 'tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost:5173',
    trace: 'retain-on-failure',
    locale: 'es-MX',
    timezoneId: 'America/Ciudad_Juarez',
    launchOptions,
  },
  projects: [
    { name: 'tablet', use: { ...devices['Desktop Chrome'], viewport: { width: 768, height: 1024 } } },
    { name: 'celular', use: { ...devices['Desktop Chrome'], viewport: { width: 360, height: 780 }, hasTouch: true } },
  ],
  webServer: [
    { command: 'php spark serve --port 8080', cwd: '../api', url: 'http://localhost:8080/api/v1/salud', reuseExistingServer: true },
    { command: 'npm run dev -- --strictPort', url: 'http://localhost:5173/app/', reuseExistingServer: true },
  ],
});
