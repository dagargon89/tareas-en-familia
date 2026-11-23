import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// La SPA se sirve en /app/ desde CodeIgniter (doc 02 §4). El build sale a
// src/api/public/app, que está ignorado en git.
export default defineConfig({
  base: '/app/',
  plugins: [react(), tailwindcss()],
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
});
