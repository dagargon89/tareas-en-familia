# Tareas en Familia

App web (PWA) para que las hijas de primaria y secundaria vean, completen y celebren sus tareas y actividades, y para que el padre o la madre las supervise y apruebe. Uso de una sola familia.

> Nombre de trabajo: **Tareas en Familia**. Se puede cambiar sin afectar la arquitectura.

## Estado de fase

| Fase | Descripción | Estado | Gate de salida |
|---|---|---|---|
| Descubrimiento | Alcance aprobado por escrito | ✅ Completa (22-nov-2026) | Plan de desarrollo aprobado |
| Fase 0 | Documentación (docs 00–08) | ✅ Completa (22-nov-2026) | Aprobación de David; cierre manual porque `verify_phase.py` aún no está en el repositorio |
| Fase 1 | Demo UX (doc 09) + freeze del contrato | 🟡 En curso | Validación con las hijas y `ApiClient` congelado en doc 05 |
| Fase 2 | Construcción por sprints (doc 10) | ⬜ Pendiente | Cada sprint al 100 % `PASS` |
| Fase 3 | Despliegue y operación (doc 11) | ⬜ Pendiente | Rollback y restauración probados |

## Stack confirmado

| Capa | Tecnología |
|---|---|
| API | CodeIgniter 4.7 · PHP 8.3 |
| Base de datos | MariaDB 10.11 o superior (la del hosting) |
| Frontend | React 19 · TypeScript 5 · Vite 7 · Tailwind CSS 4 |
| Datos en cliente | TanStack Query 5 · Zustand 5 |
| Rutas, íconos y fuente | React Router 7 · Lucide · DM Sans local con @fontsource (ADR-007) |
| Juego y animación | Motion · canvas-confetti · Howler.js |
| PWA | vite-plugin-pwa |
| Pruebas | PHPUnit 11 · Vitest 3 · Testing Library · Playwright · axe-core |
| Hosting | Hostinger Business Web Hosting (compartido, LiteSpeed) |
| CI/CD | GitHub Actions + rsync por SSH |

Descartados con ADR: Laravel, Filament, Livewire, nginx propio y Docker en producción (ver `02-arquitectura/adr/`).

## Mapa de documentos

El número de documento es global y estable. El número de carpeta solo indica orden de lectura.

| Doc | Archivo | Contenido |
|---|---|---|
| 00 | `00-fuentes/00_auditoria_fuentes.md` | Fuentes, decisiones de origen y huecos |
| 01 | `01-vision/01_srs.md` | Requisitos funcionales y no funcionales con IDs |
| 08 | `01-vision/08_design_system.md` | Dirección "Formas vivas", tokens, componentes, niveles |
| 02 | `02-arquitectura/02_arquitectura.md` | Componentes, capas, flujos, estructura del repositorio |
| ADR | `02-arquitectura/adr/` | Decisiones de arquitectura |
| 03 | `03-datos/03_modelo_datos.md` + `03_esquema.sql` + `03_esquema.json` | Modelo, DDL y espejo JSON |
| 04 | `04-seguridad/04_seguridad.md` | Amenazas, controles, privacidad de menores |
| 05 | `05-contrato/05_contrato_api.md` | Endpoints, tipos y `ApiClient` único |
| 06 | `06-pruebas/06_plan_pruebas.md` | Catálogo de casos de prueba con IDs |
| 07 | `07-roadmap/07_roadmap.md` | Sprints y fichas de tarea |
| 10 | `06-pruebas/sprints/` | Registro de ejecución por sprint (se llena en Fase 2) |
| 09 | `demo-ux/` | Especificación y prototipo (Fase 1) |
| 11 | `08-operacion/` | Despliegue y operación (Fase 3) |

## Estructura del repositorio de código

```text
src/
├── api/        ← CodeIgniter 4 (se despliega fuera de public_html)
└── web/        ← React + Vite (compila a src/api/public/app)
```

## Arranque local (resumen)

1. PHP 8.3, Composer 2, Node 22 LTS y MariaDB 10.11 instalados (Laragon o Laravel Herd en Windows).
2. `cd src/api && composer install && cp env .env` y configurar la base `tareas_dev`.
3. `php spark migrate --all && php spark db:seed DemoSeeder`.
4. `php spark serve --port 8080`.
5. `cd src/web && npm ci && npm run dev` (Vite en el puerto 5173 con proxy a `/api`).

Detalle completo en `02-arquitectura/02_arquitectura.md` §8.
