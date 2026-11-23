# CLAUDE.md · Tareas en Familia

Instrucciones para cualquier agente que desarrolle este proyecto. Léelas completas antes de tocar código.

## Estado de fase

| Fase | Estado |
|---|---|
| Descubrimiento | ✅ Completa |
| Fase 0 · Documentación | ✅ Completa (cierre manual, 22-nov-2026) |
| Fase 1 · Demo UX | ⏭️ Reducida a T-F1-01 (ADR-008) |
| Fase 2 · Construcción | 🟡 En curso (S1 a S4 completos; S5 en 13 de 15; S0 abierto por despliegue) |
| Fase 3 · Despliegue | ⬜ Pendiente |

No se trabaja en una fase cuyo gate anterior no esté cerrado. Esta tabla debe coincidir con la del README.

## Stack (confirmado, no cambiar sin ADR)

CodeIgniter 4.7 + PHP 8.3 · MariaDB 10.11+ · React 19 + TypeScript + Vite 7 + Tailwind CSS 4 · TanStack Query 5 · Zustand 5 · React Router 7 · Lucide · @fontsource/dm-sans (ADR-007) · Motion · PHPUnit 11 · Vitest 3 · Playwright. Hosting compartido Hostinger (LiteSpeed): sin nginx, Docker, Redis ni workers.

## Lectura obligatoria por tarea

1. La ficha de la tarea en `07-roadmap/07_roadmap.md`.
2. Los RF que cita en `01-vision/01_srs.md`.
3. Los endpoints que cita en `05-contrato/05_contrato_api.md`.
4. Los casos que cita en `06-pruebas/06_plan_pruebas.md`.
5. Si toca datos: `03-datos/03_modelo_datos.md`. Si toca UI: `01-vision/08_design_system.md`. Si toca auth, archivos o headers: `04-seguridad/04_seguridad.md`.

## Ramas (ADR-008)

- `main` siempre compila y pasa la suite. No se trabaja directo en `main`.
- Cada sprint tiene una rama de API (`sNN/api-<tema>`) y una de web (`sNN/web-<tema>`) que avanzan en paralelo.
- Solo la rama de API escribe en `06-pruebas/sprints/sprint-NN.md`; la de web reporta sus casos en la descripción del merge y se registran al integrar.
- Mientras su API no existe, una pantalla se ve en `/app/galeria` con datos fijos; nunca con un `ApiClient` falso.

## Orden de trabajo en cada sprint

1. Verificar que el sprint anterior está al 100 % `PASS` en `06-pruebas/sprints/`.
2. Crear `06-pruebas/sprints/sprint-NN.md` con la lista de casos del sprint en estado `PENDIENTE`.
3. Por cada ficha, en orden de dependencias: escribir primero las pruebas de sus casos (en rojo), luego el código hasta verde.
4. Correr la suite completa: `composer test` (api) y `npm test && npm run lint && npx tsc --noEmit` (web); e2e cuando la ficha lo pida.
5. Registrar cada caso como `PASS` con su evidencia (ruta del test y comando).
6. Actualizar el semáforo en `06-pruebas/sprints/README.md`.
7. Correr `python .metodologia/verify_phase.py --fase 2` y dejarlo en 0 errores (pendiente: copiar el script de la Metodología v4 al repositorio antes del Sprint 0).

## Prohibiciones

1. **No cambiar el stack** ni agregar dependencias de producción sin un ADR aprobado por David.
2. **No modificar la interfaz `ApiClient`** después del freeze (al cerrar el S2, ADR-008) sin ADR de cambio de contrato (Metodología v4 §11).
3. **No crear ni alterar tablas fuera de una migración**, y no dejar `03_esquema.sql` y `03_esquema.json` desactualizados.
4. **No acreditar puntos fuera de `PuntosService`** ni aceptar puntos enviados por el cliente.
5. **No escribir SQL concatenando entradas**; solo Query Builder o consultas con binding.
6. **No guardar datos personales de las hijas** que no estén en el doc 03 (sin fecha de nacimiento, escuela, apellidos, ubicación).
7. **No cargar recursos de terceros** en tiempo de ejecución (CDN, fuentes, analytics) ni usar `dangerouslySetInnerHTML`.
8. **No marcar un caso como `PASS` sin evidencia verificable** ni avanzar de sprint con casos pendientes.

## Reglas de código

- PHP: `declare(strict_types=1);`, tipos en firmas, Controllers sin lógica, Services con transacciones para operaciones multi-tabla.
- TypeScript: `strict: true`, sin `any`, componentes funcionales, datos del servidor solo con TanStack Query a través del `ApiClient`.
- Textos de UI en español de México, con el vocabulario del nivel (doc 08 §6). Nunca rojo de error en vistas de las hijas.
- Fechas: "hoy" siempre en `America/Ciudad_Juarez`.

## Comandos

| Acción | Comando |
|---|---|
| API local | `cd src/api && php spark serve --port 8080` |
| Migrar y sembrar | `php spark migrate --all && php spark db:seed CatalogoSeeder && php spark db:seed DemoSeeder` |
| Crear padre | `php spark usuarios:crear` |
| Tareas programadas | `php spark tareas:programadas` |
| Pruebas API | `composer test` |
| Web local | `cd src/web && npm run dev` |
| Pruebas web | `npm test`, `npm run e2e` |
| Build | `npm run build` (sale a `src/api/public/app`) |
