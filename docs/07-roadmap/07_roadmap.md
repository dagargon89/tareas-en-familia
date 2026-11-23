# 07 · Roadmap y fichas de tarea

**Proyecto:** Tareas en Familia · **Versión:** 1.0 · **Fecha:** 2026-11-22

## 1. Fases

| Fase | Duración estimada | Entregable | Gate |
|---|---|---|---|
| Fase 0 · Documentación | 1 semana | Docs 00–08, README, CLAUDE.md, ADRs | `verify_phase.py --fase 0` en 0 errores + aprobación de David |
| Fase 1 · Demo UX | 2 semanas | Doc 09 + prototipo React con `MockApiClient` de los 3 niveles | Sesión con las hijas registrada + `ApiClient` congelado |
| Fase 2 · Construcción | 10 semanas (S0 a S5) | App funcionando en subdominio de pruebas | Cada sprint al 100 % `PASS` en doc 10 |
| Fase 3 · Despliegue | 1 semana | Doc 11, producción, respaldos (diario en hosting y semanal fuera de él) | Rollback y restauración probados con tiempo medido |

Duraciones estimadas para un solo desarrollador a tiempo parcial. S0 dura 1 semana; S1 a S5, 2 semanas cada uno (S0 se cuenta dentro de la primera quincena de Fase 2).

## 2. Fase 1 · Demo UX

> **Reducida por ADR-008:** solo se ejecutó T-F1-01. T-F1-02 a T-F1-06 se omiten y T-F1-07 (freeze del contrato) pasa al cierre del S2.

| ID | Tarea | Archivos | Criterio de aceptación |
|---|---|---|---|
| T-F1-01 | Proyecto Vite + React + TS + Tailwind 4 con tokens del doc 08 | `src/web/*`, `src/web/src/styles/tokens.css` | `npm run build` sin errores; tokens visibles en una página de muestra |
| T-F1-02 | `MockApiClient` completo con datos de demo | `src/web/src/api/MockApiClient.ts`, `tipos.ts` | Implementa toda la interfaz `ApiClient` (verificado por `tsc`) |
| T-F1-03 | Pantallas de hija: selector, PIN, Mi día, celebración, logros, premios | `src/web/src/features/acceso`, `mi-dia`, `progreso`, `premios` | Navegables en los 3 niveles con `data-nivel` |
| T-F1-04 | Pantallas de padre: panel, aprobaciones, formulario de tarea | `src/web/src/features/padre/*` | Flujo aprobar → ver puntos funciona con el mock |
| T-F1-05 | Doc 09 de especificación UX | `demo-ux/09_demo_ux.md` | Cada pantalla con su RF, estados y capturas |
| T-F1-06 | Sesión de validación con las hijas | `demo-ux/validacion_sesion.md` | Hallazgos registrados y ajustes aplicados |
| T-F1-07 | Freeze del contrato | `05-contrato/05_contrato_api.md` | Versión 1.0 marcada como congelada y fecha registrada |

## 3. Fase 2 · Sprints

| Sprint | Objetivo | RF | Casos |
|---|---|---|---|
| S0 | Base técnica y despliegue a pruebas | — | CP-SEG-04, CP-SEG-06 |
| S1 | Acceso, perfiles e instalación inicial | RF-01, RF-02, RF-17, RF-20 | CP-001 a CP-016, CP-SEG-01 a CP-SEG-03, CP-SEG-07 |
| S2 | Tareas, recurrencias, Mi día y escolares propias | RF-03, RF-04, RF-05, RF-13, RF-19 | CP-020 a CP-037, CP-039, CP-SEG-05 |
| S3 | Evidencias, aprobación y puntos | RF-06, RF-07, RF-08 | CP-040 a CP-052, CP-038 |
| S4 | Recompensas, rachas, insignias, meta, configuración | RF-09 a RF-12, RF-18 | CP-060 a CP-072, CP-086 |
| S5 | Calendario, nivel Pro, panel, PWA y no funcionales | RF-14, RF-15, RF-16 | CP-080 a CP-085, CP-087, CP-SEG-08, CP-SEG-09, CP-NF-01 a CP-NF-06 |

## 4. Fichas de tarea

Formato: **Requisitos** · **Depende de** · **Casos** · **Endpoints** · **Archivos** · **Aceptación** · **Fuera de alcance**.

### S0 · Base técnica

**T-S0-01 · Esqueleto de CI4**
- Requisitos: — · Depende de: H-01, H-02 · Casos: — · Endpoints: E-01
- Archivos: `src/api/composer.json`, `app/Config/App.php` (zona `America/Ciudad_Juarez`, `defaultLocale = es-MX`), `app/Config/Routes.php` (grupo `api/v1`), `app/Controllers/Api/V1/SaludController.php`, `phpunit.xml.dist`
- Aceptación: `GET /api/v1/salud` responde `{ estado: "ok" }`; `composer test` corre.
- Fuera de alcance: autenticación.

**T-S0-02 · Migraciones y semillas**
- Requisitos: doc 03 · Depende de: T-S0-01 · Casos: — · Endpoints: —
- Archivos: `app/Database/Migrations/2026-12-01-000001_*.php` a `…000017_*.php` (16 tablas + vista), `app/Database/Seeds/CatalogoSeeder.php`, `DemoSeeder.php`, `tests/feature/EsquemaTest.php`
- Aceptación: `EsquemaTest` compara `information_schema` contra `03-datos/03_esquema.json` y pasa.
- Fuera de alcance: datos reales.

**T-S0-03 · Filters de seguridad base**
- Requisitos: doc 04 §3.4 · Depende de: T-S0-01 · Casos: CP-SEG-04, CP-SEG-06 · Endpoints: —
- Archivos: `app/Filters/SecurityHeadersFilter.php`, `app/Config/Filters.php`, `app/Config/Security.php` (CSRF por header), `public/.htaccess`, `public/robots.txt`
- Aceptación: todas las respuestas traen los headers del doc 04.

**T-S0-04 · Proyecto web integrado**
- Requisitos: doc 08 · Depende de: T-F1-01 · Casos: — · Endpoints: —
- Archivos: `src/web/vite.config.ts` (`outDir ../api/public/app`, `base /app/`, proxy), `src/web/src/api/HttpApiClient.ts` (esqueleto con CSRF y manejo de `ApiError`)
- Aceptación: `npm run build` deja la SPA en `src/api/public/app` y CI4 la sirve en `/app/`.

**T-S0-05 · Pipeline y subdominio de pruebas**
- Requisitos: doc 02 §6 · Depende de: H-03, H-04, T-S0-04 · Casos: — · Endpoints: E-01
- Archivos: `.github/workflows/ci.yml`, `.github/workflows/deploy.yml`, `scripts/deploy_remoto.sh`, `app/Commands/TareasProgramadas.php` (esqueleto con candado)
- Aceptación: push a `main` despliega en el subdominio de pruebas y `/api/v1/salud` responde por HTTPS; el cron escribe en `cron.log`.

### S1 · Acceso y perfiles

**T-S1-01 · Login y sesión del padre**
- Requisitos: RF-02 · Depende de: T-S0-02, T-S0-03 · Casos: CP-001, CP-002, CP-SEG-03, CP-SEG-07 · Endpoints: E-02 a E-05
- Archivos: `app/Services/AuthService.php`, `app/Controllers/Api/V1/AuthController.php`, `app/Filters/SesionFilter.php`, `app/Models/UsuarioModel.php`, `app/Commands/CrearUsuario.php` (alta del padre por consola; pide nombre, correo, contraseña y PIN porque `pin_hash` es obligatorio)
- Aceptación: casos en PASS; no existe endpoint de registro público (la única alta por web es la instalación inicial de T-S1-05).
- Fuera de alcance: recuperación de contraseña (se restablece por consola).

**T-S1-02 · Perfiles y PIN**
- Requisitos: RF-01, RF-17 · Depende de: T-S1-01 · Casos: CP-003 a CP-006, CP-010 a CP-012 · Endpoints: E-06 a E-08, E-23 a E-27
- Archivos: `app/Services/PinService.php` (validación de PIN trivial, bloqueo), `app/Services/PerfilesService.php`, `app/Models/PerfilModel.php`, `app/Entities/Perfil.php`, `app/Controllers/Api/V1/PerfilesController.php`, `app/Filters/PerfilActivoFilter.php`
- Aceptación: casos en PASS.

**T-S1-03 · Modo padre**
- Requisitos: RF-02 · Depende de: T-S1-02 · Casos: CP-007, CP-008, CP-SEG-01, CP-SEG-02 · Endpoints: E-09, E-10
- Archivos: `app/Filters/ModoPadreFilter.php`, `app/Config/Filters.php` (grupo `admin/*`)
- Aceptación: cualquier ruta `admin/*` sin modo padre responde 403.

**T-S1-04 · Pantallas de acceso**
- Requisitos: RF-01, RF-02, RF-17 · Depende de: T-S1-01 a T-S1-03, componentes de `s0/web-base` · Casos: CP-009 · Endpoints: los anteriores vía `HttpApiClient`
- Archivos: `src/web/src/features/acceso/*`, `src/web/src/features/padre/Perfiles.tsx`, `src/web/src/stores/sesionStore.ts`, `src/web/tests/e2e/acceso.spec.ts`
- Aceptación: CP-009 en PASS en ambos viewports.

**T-S1-05 · Instalación inicial (RF-20)**
- Requisitos: RF-20 · Depende de: T-S1-01 · Casos: CP-013 a CP-016 · Endpoints: E-49, E-50
- Archivos: `app/Services/InstalacionService.php`, `app/Controllers/Api/V1/InstalacionController.php`, `instalacion.token` en `env`, `src/web/src/features/acceso/Instalacion.tsx`
- Aceptación: casos en PASS; con un usuario existente, E-50 nunca crea nada.

### S2 · Tareas y Mi día

**T-S2-01 · Dominio de recurrencia**
- Requisitos: RF-03 · Depende de: T-S0-02 · Casos: CP-020 a CP-022, CP-034 · Endpoints: —
- Archivos: `app/Domain/ReglaRecurrencia.php`, `app/Domain/SemanaIso.php`, `tests/unit/Domain/*`
- Aceptación: 100 % de cobertura del dominio.

**T-S2-02 · CRUD de tareas y generador**
- Requisitos: RF-03, RF-13 · Depende de: T-S2-01, T-S1-03 · Casos: CP-023 a CP-026, CP-033, CP-SEG-05 · Endpoints: E-28 a E-32
- Archivos: `app/Services/TareasService.php`, `app/Services/GeneradorInstancias.php`, `app/Models/TareaModel.php`, `RecurrenciaModel.php`, `InstanciaModel.php`, `app/Controllers/Api/V1/TareasController.php`, subtarea "generar" en `TareasProgramadas.php`
- Aceptación: casos en PASS; editar recurrencia regenera solo pendientes futuras.

**T-S2-03 · Mi día y marcar hecha**
- Requisitos: RF-04, RF-05 · Depende de: T-S2-02 · Casos: CP-027 a CP-031 · Endpoints: E-11, E-12 (sin foto)
- Archivos: `app/Controllers/Api/V1/YoController.php`, `app/Services/InstanciasService.php`
- Aceptación: casos en PASS.

**T-S2-04 · UI de Mi día y formulario de tarea**
- Requisitos: RF-03, RF-04, RF-05 · Depende de: T-S2-03 · Casos: CP-032 · Endpoints: E-11, E-12, E-28 a E-32
- Archivos: `src/web/src/features/mi-dia/*`, `src/web/src/features/padre/Tareas.tsx`, `FormTarea.tsx`, `src/web/src/lib/sonidos.ts`, `src/web/tests/unit/mi-dia/*`
- Aceptación: CP-032 en PASS; sesión con las hijas registrada.

**T-S2-05 · Escolares propias (RF-19)**
- Requisitos: RF-19 · Depende de: T-S2-02 · Casos: CP-035 a CP-037, CP-039 (CP-038 se cierra en T-S3-02) · Endpoints: E-18 a E-21 (solo `escolar` para Aventureras; Pro también limitada a `escolar` hasta el S5)
- Archivos: métodos de tareas propias en `app/Controllers/Api/V1/YoController.php` y `app/Services/TareasService.php`, `src/web/src/features/mis-tareas/*`, filtro "Creadas por ellas" en `src/web/src/features/padre/Tareas.tsx`
- Aceptación: casos en PASS; el servidor nunca toma `puntos`, `perfil_id` ni `creada_por` del cliente.

### S3 · Evidencias, aprobación y puntos

**T-S3-01 · Evidencias**
- Requisitos: RF-06 · Depende de: T-S2-03, H-07 · Casos: CP-040 a CP-044 · Endpoints: E-12 (con foto), E-36
- Archivos: `app/Services/EvidenciasService.php`, `app/Controllers/Api/V1/EvidenciasController.php`, subtarea "purgar" en `TareasProgramadas.php`
- Aceptación: casos en PASS; fotos fuera de `public/`.

**T-S3-02 · Aprobación y libro de puntos**
- Requisitos: RF-07, RF-08 · Depende de: T-S2-03 · Casos: CP-045 a CP-052, CP-038 · Endpoints: E-33 a E-35, E-13 (XP, nivel)
- Archivos: `app/Services/AprobacionService.php`, `app/Services/PuntosService.php`, `app/Domain/Niveles.php`, `app/Controllers/Api/V1/AprobacionesController.php`
- Aceptación: aprobar es una transacción; doble aprobación no acredita doble.

**T-S3-03 · UI de aprobación y progreso**
- Requisitos: RF-06 a RF-08 · Depende de: T-S3-01, T-S3-02 · Casos: — (cubiertos por e2e de S5) · Endpoints: E-12, E-13, E-33 a E-36
- Archivos: `src/web/src/features/padre/Aprobaciones.tsx`, `src/web/src/features/progreso/XpWaveBar.tsx`, `StatTile.tsx`, captura de foto en `DoneButton.tsx`
- Aceptación: flujo completo funciona en el subdominio de pruebas.

### S4 · Juego

**T-S4-01 · Recompensas y canjes**
- Requisitos: RF-09 · Depende de: T-S3-02 · Casos: CP-060 a CP-064 · Endpoints: E-14 a E-16, E-37 a E-41
- Archivos: `app/Services/CanjesService.php`, `app/Controllers/Api/V1/RecompensasController.php`, `CanjesController.php`, `src/web/src/features/premios/*`, `src/web/src/features/padre/Recompensas.tsx`
- Aceptación: casos en PASS.

**T-S4-02 · Rachas e insignias**
- Requisitos: RF-10, RF-11 · Depende de: T-S3-02 · Casos: CP-065 a CP-070 · Endpoints: E-13
- Archivos: `app/Services/RachasService.php`, `app/Services/InsigniasService.php`, subtarea "cerrar semana" en `TareasProgramadas.php`, `src/web/src/features/progreso/BadgeGrid.tsx`, `StreakBadge.tsx`
- Aceptación: casos en PASS.

**T-S4-03 · Meta familiar y configuración**
- Requisitos: RF-12, RF-18 · Depende de: T-S3-02 · Casos: CP-071, CP-072, CP-086 · Endpoints: E-22, E-42 a E-44, E-47, E-48
- Archivos: `app/Services/MetasService.php`, `app/Controllers/Api/V1/MetasController.php`, `ConfiguracionController.php`, `src/web/src/features/progreso/FamilyGoalBar.tsx`, `src/web/src/features/padre/Metas.tsx`, `Configuracion.tsx`
- Aceptación: casos en PASS.

### S5 · Cierre funcional

**T-S5-01 · Calendario semanal**
- Requisitos: RF-14 · Depende de: T-S2-03 · Casos: CP-080, CP-081 · Endpoints: E-17, E-46
- Archivos: `src/web/src/features/calendario/WeekCalendar.tsx`, método `semana()` en `InstanciasService.php`

**T-S5-02 · Nivel Pro**
- Requisitos: RF-15 · Depende de: T-S2-05 · Casos: CP-082 a CP-084 · Endpoints: E-18 a E-21 (amplía a cualquier tipo para Pro)
- Archivos: métodos de tareas propias en `YoController.php` y `TareasService.php`, `src/web/src/features/pro/*`

**T-S5-03 · Panel del padre**
- Requisitos: RF-16 · Depende de: S4 · Casos: CP-085, CP-087 · Endpoints: E-45
- Archivos: `app/Services/PanelService.php`, `app/Controllers/Api/V1/PanelController.php`, `src/web/src/features/padre/Panel.tsx`, `src/web/tests/e2e/padre.spec.ts`

**T-S5-04 · PWA, accesibilidad y rendimiento**
- Requisitos: RNF-01, RNF-03 a RNF-06, RNF-10 · Depende de: todo lo anterior · Casos: CP-SEG-08, CP-SEG-09, CP-NF-01 a CP-NF-06 · Endpoints: —
- Archivos: `vite.config.ts` (vite-plugin-pwa), `src/web/public/manifest.webmanifest`, íconos, `src/web/tests/e2e/a11y.spec.ts`, `rendimiento.spec.ts`
- Aceptación: todos los casos no funcionales en PASS; listo para Fase 3.

## 5. Definición de terminado (por tarea)

1. Casos de la ficha en PASS con evidencia registrada en el doc 10.
2. `composer test`, `npm test`, `npm run lint` y `npx tsc --noEmit` sin errores.
3. Sin `TODO` ni placeholders en código crítico.
4. Si cambió el esquema: migración nueva, `03_esquema.sql` y `03_esquema.json` actualizados y `EsquemaTest` en PASS.
5. Si cambió el contrato después del freeze: ADR de cambio de contrato (Metodología v4 §11).
