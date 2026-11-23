import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

// La SPA se sirve en /app/ desde CodeIgniter (doc 02 §4). El build sale a
// src/api/public/app, que está ignorado en git.
export default defineConfig({
  base: '/app/',
  plugins: [
    react(),
    tailwindcss(),
    // PWA instalable (RNF-10, T-S5-04). Solo se guarda en caché la app (código, fuentes,
    // íconos y sonidos); los datos de /api siempre van a la red: son de menores y cambian.
    VitePWA({
      registerType: 'autoUpdate',
      // Registro con un archivo propio (registerSW.js): la CSP no permite scripts en línea.
      injectRegister: 'script',
      includeAssets: ['iconos/apple-touch-icon.png', 'sonidos/*.wav'],
      manifest: {
        name: 'Tareas en Familia',
        short_name: 'Tareas',
        description: 'Las misiones de la familia: ver, completar y celebrar.',
        lang: 'es-MX',
        start_url: '/app/',
        scope: '/app/',
        display: 'standalone',
        orientation: 'any',
        background_color: '#FBFAFF',
        theme_color: '#FBFAFF',
        icons: [
          { src: 'iconos/icono-192.png', sizes: '192x192', type: 'image/png' },
          { src: 'iconos/icono-512.png', sizes: '512x512', type: 'image/png' },
          { src: 'iconos/icono-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,woff2,png,svg,wav}'],
        navigateFallback: '/app/index.html',
        navigateFallbackDenylist: [/^\/api\//],
        cleanupOutdatedCaches: true,
      },
    }),
  ],
  build: {
    outDir: '../api/public/app',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://localhost:8080',
    },
  },
  // `vite preview` sirve el build con respaldo de SPA y el mismo proxy: lo usa la prueba de rendimiento.
  preview: {
    port: 4173,
    proxy: {
      '/api': 'http://localhost:8080',
    },
  },
});
