# Sprint 04 · Recompensas, rachas, insignias, meta y configuración

Fechas: 2026-11-23 a —

Ramas: `s4/api-juego` (T-S4-01, T-S4-02, T-S4-03) · UI del S4 ya integrada en `main` (`s4/web-juego`).

| Caso | RF | Estado | Evidencia | Fecha |
|---|---|---|---|---|
| CP-060 | RF-09 | PASS | `src/api/tests/feature/Juego/CanjesTest.php::testSolicitarCanjeConSaldoSuficiente` · `cd src/api && composer test -- --filter testSolicitarCanjeConSaldoSuficiente` | 2026-11-23 |
| CP-061 | RF-09 | PASS | `src/api/tests/feature/Juego/CanjesTest.php::testSolicitarCanjeSinSaldoContandoReservados` · `cd src/api && composer test -- --filter testSolicitarCanjeSinSaldoContandoReservados` | 2026-11-23 |
| CP-062 | RF-09 | PASS | `src/api/tests/feature/Juego/CanjesTest.php::testAprobarCanjeInsertaMovimientoNegativo` · `cd src/api && composer test -- --filter testAprobarCanjeInsertaMovimientoNegativo` | 2026-11-23 |
| CP-063 | RF-09 | PASS | `src/api/tests/feature/Juego/CanjesTest.php::testRechazarCanjeLiberaElSaldo` · `cd src/api && composer test -- --filter testRechazarCanjeLiberaElSaldo` | 2026-11-23 |
| CP-064 | RF-09 | PASS | `src/web/tests/unit/componentes.test.tsx` › `RewardCard (CP-064)` (de `s4/web-juego`, ya en `main`) · `cd src/web && npx vitest run tests/unit/componentes.test.tsx -t "CP-064"` → 2 passed (Node 22) | 2026-11-23 |
| CP-065 | RF-10 | PASS | `src/api/tests/unit/Domain/RachasTest.php::testSemanaConOchoDeDiezYUmbralOchentaEsCumplida` · `cd src/api && composer test -- --filter testSemanaConOchoDeDiezYUmbralOchentaEsCumplida` | 2026-11-23 |
| CP-066 | RF-10 | PASS | `src/api/tests/unit/Domain/RachasTest.php::testRachaSeCuentaDesdeLaUltimaSemanaHaciaAtras` · `cd src/api && composer test -- --filter testRachaSeCuentaDesdeLaUltimaSemanaHaciaAtras` | 2026-11-23 |
| CP-067 | RF-10 | PASS | `src/api/tests/unit/Domain/RachasTest.php::testSemanaSinInstanciasNoRompeNiSuma` · `cd src/api && composer test -- --filter testSemanaSinInstanciasNoRompeNiSuma` | 2026-11-23 |
| CP-068 | RF-10 | PASS | `src/api/tests/feature/Juego/RachasInsigniasTest.php::testRomperRachaNoCreaMovimientoNegativo` · `cd src/api && composer test -- --filter testRomperRachaNoCreaMovimientoNegativo` | 2026-11-23 |
| CP-069 | RF-11 | PASS | `src/api/tests/feature/Juego/RachasInsigniasTest.php::testPrimeraAprobacionOtorgaPrimeraMision` · `cd src/api && composer test -- --filter testPrimeraAprobacionOtorgaPrimeraMision` | 2026-11-23 |
| CP-070 | RF-11 | PASS | `src/api/tests/feature/Juego/RachasInsigniasTest.php::testEvaluarInsigniasDosVecesUnaSolaFila` · `cd src/api && composer test -- --filter testEvaluarInsigniasDosVecesUnaSolaFila` | 2026-11-23 |
| CP-071 | RF-12 | PASS | `src/api/tests/feature/Juego/MetasTest.php::testSegundaMetaActivaEs409` · `cd src/api && composer test -- --filter testSegundaMetaActivaEs409` | 2026-11-23 |
| CP-072 | RF-12 | PASS | `src/api/tests/feature/Juego/MetasTest.php::testDosHijasLleganAlObjetivo` · `cd src/api && composer test -- --filter testDosHijasLleganAlObjetivo` | 2026-11-23 |
| CP-086 | RF-18 | PASS | `src/api/tests/feature/Juego/ConfiguracionTest.php::testElSiguienteCierreUsaElUmbralNuevo` · `cd src/api && composer test -- --filter testElSiguienteCierreUsaElUmbralNuevo` | 2026-11-23 |

Suite completa de `s4/api-juego` (2026-11-23): `cd src/api && composer test` → `OK (305 tests, 2098 assertions)` con PHPUnit 11.5.58, PHP 8.5.12 y MariaDB 10.11.19 (`tareas_test`). Web sin cambios en esta rama: `npm test` → 93 de 93 (Node 22).

Prueba de humo contra el servidor real (`php spark serve`, base `tareas_dev` sembrada, cookie y CSRF como el `HttpApiClient`): configuración (E-47, E-48), segunda meta → 409 `ESTADO_INVALIDO`, alta de recompensa, tarea de Sol marcada y aprobada (7 puntos y "Primera misión"), catálogo con `alcanza`/`faltan`, canje 201, canje sin saldo → 409 `SALDO_INSUFICIENTE`, preferencias (E-22), progreso (XP 27, disponible 22, meta 7), canje aprobado y entregado. Todas las respuestas con el código esperado.

## Fichas

| Ficha | Estado | Evidencia |
|---|---|---|
| T-S4-01 | Hecha | `CanjesService`, `RecompensasController`, `CanjesController`, E-14 a E-16 en `YoController`; `tests/feature/Juego/CanjesTest.php` (13 pruebas) |
| T-S4-02 | Hecha | `App\Domain\Rachas`, `RachasService`, `InsigniasService`, `MetricasPerfil`, subtarea "cerrar semana" en `TareasProgramadas`; `tests/unit/Domain/RachasTest.php`, `tests/feature/Juego/RachasInsigniasTest.php` |
| T-S4-03 | Hecha | `MetasService`, `ConfiguracionService`, `MetasController`, `ConfiguracionController`, E-22 en `PerfilesService::preferencias()`, subtarea "vencer metas"; `tests/feature/Juego/MetasTest.php`, `tests/feature/Juego/ConfiguracionTest.php` |

Sin cambios de esquema ni de contrato: no hubo migraciones y las formas de respuesta son las del doc 05 §3.

## Hallazgos y ajustes

1. **Pruebas del S3 ajustadas al S4.** El gancho `alAprobar()` del S3 no otorgaba nada; ahora evalúa insignias y meta. En `AprobacionTest`: el helper `movimientos()` cuenta solo los de origen `tarea` (lo que verifica), la primera aprobación espera `insignias_nuevas = [primera_mision]` y CP-051 usa el flujo real de canje (E-15 y E-41) en vez de insertar el movimiento a mano, con XP 30 (10 de la tarea + 20 de la insignia). La nota de evidencia de CP-051 en `sprint-03.md` ("se inserta desde la prueba") quedó superada.
2. **E-22 en Exploradoras solo acepta `tema = claro`** (doc 08 §3: sin modo oscuro en ese nivel; `sistema` también podría dar oscuro). Otro valor responde 400 `VALIDACION` en `tema`. Aclaración de comportamiento sin cambio de forma; la web ya no ofrece el cambio de tema en Exploradoras.
3. **Progreso de una meta terminada se congela**: en `cumplida` cuenta hasta `cumplida_en` y en `cancelada` hasta `updated_at`; en `activa` no tiene tope. Bajar el objetivo por debajo del progreso no la cumple en ese momento: se cumple en la siguiente aprobación. Los XP de insignias no suman a la meta (doc 03 §4).
4. **E-44 solo edita la meta `activa`** y el padre solo puede pasarla a `cancelada`; editar una cumplida o cancelada, o pedir `cumplida`, responde 409 `ESTADO_INVALIDO`. Un estado fuera del enum responde 400.
5. **Cierre semanal en el cron**: cierra la semana ISO anterior en la primera ejecución en que cambia la semana (marca `writable/cache/cierre.ultimo`), con el `racha_umbral` vigente al cerrar (CP-086). Es idempotente (`uq_rachas_perfil_semana` con `INSERT IGNORE`). Las aprobaciones de esa semana hechas después del cierre no recalculan la fila. Evalúa insignias solo de los perfiles con fila nueva.
6. **Vencimiento de metas**: corre en cada ejecución del cron (una sola actualización sobre una tabla mínima) y cancela la meta activa con `fecha_limite` anterior a hoy; la meta vale todo su último día.
7. **Canjes**: pedir una recompensa inactiva o inexistente responde 404 `NO_ENCONTRADO`. `solicitar()` bloquea la fila del perfil (`FOR UPDATE`) para que dos canjes simultáneos no gasten el mismo disponible. Orden: E-16 el más reciente primero; E-40 el más antiguo primero (cola por revisar); E-37 y E-14 del más barato al más caro. Costo de 1 a 9999 (igual que la web).
8. **`PuntosService::registrar()` pasó a privado.** Solo se escribe en el libro por `acreditarTarea`, `acreditarInsignia` y `debitarCanje` (prohibición 4 de `CLAUDE.md`).
9. **Entorno local de esta corrida**: MariaDB 10.11.19 en Docker (`127.0.0.1:3317`) con un `phpunit.xml` local (ignorado por git) que apunta ahí; con MySQL 8+ fallan `EsquemaTest` y `SemillasTest` por diferencias de `information_schema` y del orden de llaves JSON. La web necesita Node 22 (con Node 24, 4 pruebas de `acceso` fallan por `AbortSignal`).
10. **Pendientes de verificación fuera de esta rama**: cobertura ≥ 85 % en `app/Services` y `app/Domain` (RNF-09) sin medir, porque el PHP local no tiene Xdebug ni PCOV (igual que en S2 y S3, queda para el CI de T-S0-05). Los e2e de Playwright no se corrieron: la versión instalada pide otro Chromium (`npx playwright install chromium`) y las fichas del S4 no piden e2e.
