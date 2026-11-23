# Sprint 01 · Acceso y perfiles

Fechas: 2026-11-23 a 2026-11-23

Ramas: `s1/api-acceso` (T-S1-01, T-S1-02, T-S1-03, T-S1-05) · rama web del S1 (T-S1-04 y la pantalla de T-S1-05).

| Caso | RF | Estado | Evidencia | Fecha |
|---|---|---|---|---|
| CP-001 | RF-02 | PASS | `src/api/tests/feature/Acceso/AuthTest.php::testLoginValidoAbreLaSesionDelDispositivo` · `cd src/api && composer test -- --filter testLoginValidoAbreLaSesionDelDispositivo` | 2026-11-23 |
| CP-002 | RF-02 | PASS | `src/api/tests/feature/Acceso/AuthTest.php::testLoginConContrasenaIncorrecta` · `cd src/api && composer test -- --filter testLoginConContrasenaIncorrecta` | 2026-11-23 |
| CP-003 | RF-01 | PASS | `src/api/tests/feature/Acceso/PerfilesTest.php::testPerfilesSinSesion` · `cd src/api && composer test -- --filter testPerfilesSinSesion` | 2026-11-23 |
| CP-004 | RF-01 | PASS | `src/api/tests/feature/Acceso/PerfilesTest.php::testEntrarConPinCorrecto` · `cd src/api && composer test -- --filter testEntrarConPinCorrecto` | 2026-11-23 |
| CP-005 | RF-01 | PASS | `src/api/tests/feature/Acceso/PerfilesTest.php::testCincoPinIncorrectosBloquean15Minutos` · `cd src/api && composer test -- --filter testCincoPinIncorrectosBloquean15Minutos` | 2026-11-23 |
| CP-006 | RF-01 | PASS | `src/api/tests/feature/Acceso/PerfilesTest.php::testPinCorrectoDuranteElBloqueo` · `cd src/api && composer test -- --filter testPinCorrectoDuranteElBloqueo` | 2026-11-23 |
| CP-007 | RF-02 | PASS | `src/api/tests/feature/Acceso/ModoPadreTest.php::testEntrarConPinDelPadre` · `cd src/api && composer test -- --filter testEntrarConPinDelPadre` | 2026-11-23 |
| CP-008 | RF-02 | PASS | `src/api/tests/unit/Filters/AccesoFiltersTest.php::testModoPadreExpiraTrasDiezMinutosSinActividad` · `cd src/api && composer test -- --filter testModoPadreExpiraTrasDiezMinutosSinActividad` | 2026-11-23 |
| CP-009 | RF-01 | PASS | `src/web/tests/e2e/acceso.spec.ts` contra la API real y `tareas_dev` sembrada, viewports 768×1024 y 360×780 · `cd src/web && npm run e2e` (2 passed) | 2026-11-23 |
| CP-010 | RF-17 | PASS | `src/api/tests/feature/Acceso/AdminPerfilesTest.php::testCrearPerfilConPinTrivial` · `cd src/api && composer test -- --filter testCrearPerfilConPinTrivial` | 2026-11-23 |
| CP-011 | RF-17 | PASS | `src/api/tests/feature/Acceso/AdminPerfilesTest.php::testDesbloquearPerfil` · `cd src/api && composer test -- --filter testDesbloquearPerfil` | 2026-11-23 |
| CP-012 | RF-17 | PASS | `src/api/tests/feature/Acceso/AdminPerfilesTest.php::testBorradoDefinitivo` · `cd src/api && composer test -- --filter testBorradoDefinitivo` | 2026-11-23 |
| CP-013 | RF-20 | PASS | `src/api/tests/feature/Acceso/InstalacionTest.php::testEstadoConCeroYConUnUsuario` · `cd src/api && composer test -- --filter testEstadoConCeroYConUnUsuario` | 2026-11-23 |
| CP-014 | RF-20 | PASS | `src/api/tests/feature/Acceso/InstalacionTest.php::testInstalacionValidaCreaElUsuarioYAbreLaSesion` · `cd src/api && composer test -- --filter testInstalacionValidaCreaElUsuarioYAbreLaSesion` | 2026-11-23 |
| CP-015 | RF-20 | PASS | `src/api/tests/feature/Acceso/InstalacionTest.php::testConUsuarioExistenteEs409` · `cd src/api && composer test -- --filter testConUsuarioExistenteEs409` | 2026-11-23 |
| CP-016 | RF-20 | PASS | `src/api/tests/feature/Acceso/InstalacionTest.php::testProduccionSinTokenOConTokenIncorrecto` · `cd src/api && composer test -- --filter testProduccionSinTokenOConTokenIncorrecto` | 2026-11-23 |
| CP-SEG-01 | RNF-07 | PASS | `src/api/tests/feature/Acceso/AuthTest.php::testPostSinTokenCsrfEs403` · `cd src/api && composer test -- --filter testPostSinTokenCsrfEs403` | 2026-11-23 |
| CP-SEG-02 | RNF-07 | PASS | `src/api/tests/feature/Acceso/ModoPadreTest.php::testHijaConPerfilActivoNoEntraAAdmin` · `cd src/api && composer test -- --filter testHijaConPerfilActivoNoEntraAAdmin` | 2026-11-23 |
| CP-SEG-03 | RNF-07 | PASS | `src/api/tests/feature/Acceso/AuthTest.php::testSextoLoginFallidoEnUnMinutoEsThrottled` · `cd src/api && composer test -- --filter testSextoLoginFallidoEnUnMinutoEsThrottled` | 2026-11-23 |
| CP-SEG-07 | RNF-07 | PASS | `src/api/tests/feature/Acceso/AuthTest.php::testCookieDeSesionEnProduccion` · `cd src/api && composer test -- --filter testCookieDeSesionEnProduccion` · además, servidor real con `CI_ENVIRONMENT = production`: `Set-Cookie: tf_sesion=…; Max-Age=2592000; path=/; secure; HttpOnly; SameSite=Lax` | 2026-11-23 |

Suite completa de `s1/api-acceso` (2026-11-23, tras integrar `main` con RF-19 y RF-20): `cd src/api && composer test` → `OK (141 tests, 864 assertions)` con PHPUnit 11.5.58, PHP 8.5.12 y MariaDB 10.11.20.

## Hallazgos y ajustes

1. **El S1 arrancó antes de cerrar el S0** por decisión de David: T-S0-04 y T-S0-05 siguen abiertas (T-S0-05 bloqueada por H-03 y H-04). Excepción explícita al gate de `CLAUDE.md` (orden de trabajo, paso 1).
2. **`bloqueado_hasta` vencido se expone como `null`** en `PerfilPublico`, `PerfilAdmin` y en la sesión, aunque la columna conserve la fecha pasada; el contrato no lo precisaba. Al bloquear, `pin_intentos` vuelve a 0 (los 5 intentos se cuentan de nuevo al vencer el bloqueo).
3. **Rutas `admin/*` inexistentes**: un comodín al final del grupo `admin` pasa por `sesion` y `modoPadre`, así que sin modo padre responde 403 `MODO_PADRE_REQUERIDO` y con modo padre 404 `NO_ENCONTRADO`. Las rutas admin nuevas deben declararse antes del comodín (comentario en `Config/Routes.php`). Así se prueba CP-SEG-02 con `GET /admin/aprobaciones`, que aún no existe.
4. **Borrado definitivo (CP-012)**: las filas se borran en una transacción en el orden del doc 03 §4.1 y los archivos de `writable/evidencias/<perfil_id>/` se borran *después* del commit, no al inicio como dice el doc 03 §4.1: si la base fallara, no quedarían filas apuntando a fotos borradas. Propuesta: ajustar la redacción del doc 03 §4.1.
5. **Throttling compartido**: login (E-03) e instalación (E-50) usan el mismo contador de 5 intentos por minuto por IP (`App\Libraries\LimiteIntentos`); cuenta todos los intentos, no solo los fallidos. La respuesta 429 incluye `Retry-After`.
6. **Instalación (RF-20)**: `Config\Instalacion` lee `instalacion.token` del `.env`; en producción el token siempre se exige y, si está vacío, E-50 responde 403 (no queda abierta por olvido). La creación se serializa con `GET_LOCK` además del `SELECT … FOR UPDATE` que pide el doc 05, porque dos bloqueos de hueco sobre una tabla vacía son compatibles entre sí y acabarían en un deadlock (500) en vez de un 409. Orden de comprobación: 429 → 409 → 403 → 400.
7. **Validación**: los Services validan con comprobaciones explícitas de tipo (p. ej. `activo` debe ser booleano JSON, `orden` entero 0–255, `pin` cadena de 4 dígitos) en lugar de las reglas de `Validation` de CI4 que cita el doc 04 §3.3, porque estas no distinguen `true` de `"1"`. Los mensajes van por campo, sin la regla interna.
8. **`php spark usuarios:crear`** pide los datos con `CLI::prompt` (la contraseña se ve al escribirla; CI4 no tiene entrada oculta) o los toma de `--nombre --email --password --pin`. Rechaza un tercer usuario (máximo 2, doc 03 §3).
9. **Pruebas**: `tests/_support/ApiTestCase.php` vacía y vuelve a sembrar la base antes de cada caso, conserva la sesión entre peticiones y recrea el `Throttler` (el servicio compartido retenía la caché simulada de la prueba anterior). `DemoSeeder` reutiliza los hashes ya calculados en el mismo proceso para que la suite no tarde más de un minuto.

- CP-009: Chromium de Playwright en WSL necesita `libnss3`, `libnspr4` y `libasound2`. Sin sudo, se instalaron con Homebrew y se enlazan en `~/.local/lib/playwright-libs`; `playwright.config.ts` los usa solo si esa carpeta existe. En CI (GitHub Actions) se usa `npx playwright install --with-deps chromium`.
