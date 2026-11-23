# ADR-007 · Dependencias de producción del frontend no listadas en el stack

**Estado:** Aceptada · **Fecha:** 2026-11-22 · **Decide:** David García

## Contexto

El doc 08 usa íconos Lucide y la tipografía DM Sans servida desde el propio dominio, y el doc 02 define rutas `/app/*` con `router.tsx`. Ninguna de esas piezas estaba en el stack confirmado, y `CLAUDE.md` exige un ADR para agregar dependencias de producción.

## Decisión

| Paquete | Uso | Licencia | Motivo |
|---|---|---|---|
| `react-router` 7 (modo librería) | Rutas de la SPA bajo `/app/` | MIT | Estándar de facto; sin servidor propio |
| `lucide-react` | Catálogo de íconos de tareas | ISC | Se importan solo los íconos usados (tree-shaking), sin CDN |
| `@fontsource/dm-sans` | DM Sans 400, 500 y 700 | OFL-1.1 | Archivos `woff2` empaquetados por Vite y servidos desde `/app/`; cumple RNF-06 sin Google Fonts |

Herramientas solo de desarrollo (no requieren ADR, se registran por transparencia): `@tailwindcss/vite`, `@vitejs/plugin-react`, ESLint 9 con `typescript-eslint`, `eslint-plugin-react-hooks`.

## Alternativas descartadas

- **TanStack Router:** más tipado, pero agrega curva y no aporta en una SPA de 12 rutas.
- **Íconos SVG propios:** más trabajo sin beneficio; Lucide cubre el catálogo de 40 íconos.
- **Fuente copiada a mano en `public/`:** funciona igual, pero `@fontsource` deja la versión fijada en `package-lock.json`.

## Consecuencias

- (+) Ningún recurso de terceros en tiempo de ejecución; todo sale del bundle.
- (−) Tres dependencias más que actualizar.
