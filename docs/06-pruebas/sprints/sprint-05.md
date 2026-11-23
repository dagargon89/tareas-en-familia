# Sprint 05 · Calendario, nivel Pro, panel, PWA y no funcionales

Fechas: 2026-11-23 a —

Ramas: `s5/api-cierre` (T-S5-01 a T-S5-03, `padre.spec.ts` y evidencia de T-S5-04) · UI del S5 ya integrada en `main` (`s5/web-cierre`).

| Caso | RF / RNF | Estado | Evidencia | Fecha |
|---|---|---|---|---|
| CP-080 | RF-14 | PASS | `src/api/tests/feature/Calendario/SemanaTest.php::testSemanaDeLaHijaConSieteDias` · `cd src/api && composer test -- --filter testSemanaDeLaHijaConSieteDias` | 2026-11-23 |
| CP-081 | RF-14 | PASS | `src/api/tests/feature/Calendario/SemanaTest.php::testActividadSinPuntosSeApruebaSola` · `cd src/api && composer test -- --filter testActividadSinPuntosSeApruebaSola` | 2026-11-23 |
| CP-082 | RF-15 | PASS | `src/api/tests/feature/Tareas/TareasProTest.php::testProCreaTareaPropiaDeCasa` · `cd src/api && composer test -- --filter testProCreaTareaPropiaDeCasa` | 2026-11-23 |
| CP-083 | RF-15 | PASS | `src/api/tests/feature/Tareas/TareasProTest.php::testAventureraNoCreaActividad` · `cd src/api && composer test -- --filter testAventureraNoCreaActividad` | 2026-11-23 |
| CP-084 | RF-15 | PASS | `src/api/tests/feature/Tareas/TareasProTest.php::testProNoEditaTareaDelPadre` · `cd src/api && composer test -- --filter testProNoEditaTareaDelPadre` | 2026-11-23 |
| CP-085 | RF-16 | PASS | `src/api/tests/feature/Padre/PanelTest.php::testPanelConDatosDeDemo` · `cd src/api && composer test -- --filter testPanelConDatosDeDemo` | 2026-11-23 |
| CP-087 | RF-16 | PASS | `src/web/tests/e2e/padre.spec.ts` contra la API real, tablet y celular · `cd src/web && npx playwright test tests/e2e/padre.spec.ts` → 2 passed | 2026-11-23 |
| CP-SEG-08 | RNF-07 | PENDIENTE | Manual en el servidor: requiere el subdominio de pruebas (T-S0-05). Localmente, `tests/unit/Seguridad/ArchivosPublicosTest.php` verifica los `.htaccess` | — |
| CP-SEG-09 | RNF-07 | PASS | `src/web/tests/e2e/build/rendimiento.spec.ts` (build de producción, sin peticiones a otro origen) · `cd src/web && npx playwright test --project=build` → 1 passed | 2026-11-23 |
| CP-NF-01 | RNF-01 | PASS | `src/web/tests/e2e/build/rendimiento.spec.ts` · LCP de Mi día 820 ms y 860 ms en dos corridas (4G, CPU 4×, caché vacía; máximo 1500 ms) | 2026-11-23 |
| CP-NF-02 | RNF-03 | PASS | `src/web/tests/e2e/botones.spec.ts` (alto de "Listo" por nivel), tablet y celular → 2 passed | 2026-11-23 |
| CP-NF-03 | RNF-04 | PASS | `src/web/tests/e2e/a11y.spec.ts`: axe sin violaciones serias ni críticas en las pantallas de los 3 niveles y del padre (incluidas las del S4 y el S5 ya con su API real), tablet y celular → 10 passed | 2026-11-23 |
| CP-NF-04 | RNF-05 | PASS | Suite e2e completa en 768 px y 360 px más el build · `cd src/web && npx playwright test` → `17 passed (1.3m)` | 2026-11-23 |
| CP-NF-05 | RNF-10 | PENDIENTE | Manual: instalar la PWA en Android y en iPhone; requiere HTTPS en el subdominio de pruebas (T-S0-05) | — |
| CP-NF-06 | RNF-09 | PASS | PCOV en PHP 8.3.37 (contenedor `php:8.3-cli` + pcov, mysqli, intl, gd, exif) contra el mismo MariaDB: `OK (324 tests, 2295 assertions)` · `app/Domain` 104/104 líneas (100 %), `app/Services` 1293/1359 (95,14 %), juntos 1397/1463 (95,49 %); ningún archivo por debajo de 85 % · `vendor/bin/phpunit --coverage-clover …` | 2026-11-23 |

Suite completa de `s5/api-cierre` (2026-11-23): `cd src/api && composer test` → `OK (324 tests, 2295 assertions)` con PHPUnit 11.5.58 sobre PHP 8.5.12 y también sobre PHP 8.3.37 (la del hosting), MariaDB 10.11.19. Web: `npm test` 93 de 93, `npm run lint` y `tsc` sin errores; e2e 17 de 17 (Node 22, Playwright 1.65.1, Chromium headless 1255).

## Fichas

| Ficha | Estado | Evidencia |
|---|---|---|
| T-S5-01 | Hecha | `InstanciasService::semana()` y `semanaAdmin()`, E-17 en `YoController`, E-46 en `PanelController`; `tests/feature/Calendario/SemanaTest.php` (8 pruebas) |
| T-S5-02 | Hecha | Rutinas de Pro en `TareasService` (`validarRutina`, `exigirPro`); `tests/feature/Tareas/TareasProTest.php` (8 pruebas) |
| T-S5-03 | Hecha | `PanelService`, `PanelController` (E-45); `tests/feature/Padre/PanelTest.php`, `src/web/tests/e2e/padre.spec.ts` |
| T-S5-04 | Abierta | Casos automáticos en PASS; faltan CP-SEG-08 y CP-NF-05, que necesitan el subdominio de pruebas con HTTPS (T-S0-05) |

## Hallazgos y ajustes

1. **El S5 no puede cerrar al 100 % sin desplegar.** CP-SEG-08 (pedir `/writable/…` y `/.env` en el servidor) y CP-NF-05 (instalar la PWA en Android y iPhone) son manuales y necesitan el subdominio de pruebas con HTTPS, que es T-S0-05. El S0 sigue abierto por esa misma ficha. Siguiente paso: T-S0-05 (pipeline, despliegue y cron), y después estos dos casos.
2. **Rutinas de Pro (aclaración en doc 05 §2.2).** El 409 "ya aprobada" se aplica a las propias de una sola vez. Una rutina diaria o semanal se puede editar o desactivar con instancias aprobadas; como en las del padre, solo cambian o se borran las pendientes desde hoy. Una escolar no cambia a otro tipo ni al revés. La rutina usa las reglas de `TareaEntrada` del padre, con perfil, puntos (`puntos_tarea_propia`, también en actividades) y categoría por tipo fijados por el servidor. `fecha_inicio` no puede ser anterior a hoy al crear. Se quitó `TareasPropiasTest::testProTambienLimitadaAEscolarEnElS2`, que afirmaba la restricción del S2 que el S5 levanta.
3. **Semana (aclaración en doc 05).** `desde` se normaliza al lunes de su semana ISO; cada instancia aparece solo en su `fecha`. Como "Mi día", la consulta genera las instancias que falten solo dentro de una semana antes y después de hoy. E-46 sin `perfil_id` trae las hijas activas; con `perfil_id` inexistente responde 404 y con uno no numérico, 400.
4. **La aprobación automática (actividad de 0 puntos) ahora evalúa insignias**, igual que la del padre (doc 03 §5). No acredita puntos ni suma a la meta. `MiDiaTest::testActividadDeCeroPuntosQuedaAprobada` cuenta ahora solo los movimientos de origen `tarea`.
5. **Panel**: `porcentaje_semana` se calcula en vivo (aprobadas sobre asignadas de la semana pedida, 2 decimales). `por_aprobar` cuenta todas las `hecha` de la hija, de cualquier fecha. `racha_semanas` es la racha actual, sin importar la semana pedida. `proximas_entregas` trae hasta 5 escolares activas sin aprobar con entrega desde hoy. Las hijas salen en el orden de los perfiles, nunca por desempeño (RN-02).
6. **`ApiException::getCampos()`** se agregó para combinar los errores de validación de la rutina con los de `TareaEntrada` en una sola respuesta 400.
7. **e2e**: `ayuda.ts` exporta `api()` (petición con CSRF renovado) para preparar datos. `padre.spec.ts` crea sus propias tareas con títulos únicos, así que se puede repetir sin resembrar. Playwright 1.65.1 pide Chromium headless 1255 (`npx playwright install chromium`).
8. **Cobertura (CP-NF-06)** medida por primera vez: el PHP local (Herd Lite, musl) no admite Xdebug ni PCOV, así que se midió en un contenedor PHP 8.3 con PCOV. La imagen se construye con un Dockerfile de 6 líneas (`php:8.3-cli` + `pcov`, `mysqli`, `intl`, `gd` con JPEG y WebP, `exif`). Conviene llevarlo al CI de T-S0-05.
