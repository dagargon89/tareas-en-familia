# Sprint 02 · Tareas y Mi día

Fechas: 2026-11-23 a 2026-11-23

Ramas: `s2/api-tareas` (T-S2-01, T-S2-02, T-S2-03, T-S2-05) · frontend del S2 ya integrado en `main` (T-S2-04 y la UI de T-S2-05).

| Caso | RF | Estado | Evidencia | Fecha |
|---|---|---|---|---|
| CP-020 | RF-03 | PASS | `src/api/tests/unit/Domain/ReglaRecurrenciaTest.php::testDiariaDelUnoAlSiete` · `cd src/api && composer test -- --filter testDiariaDelUnoAlSiete` | 2026-11-23 |
| CP-021 | RF-03 | PASS | `src/api/tests/unit/Domain/ReglaRecurrenciaTest.php::testSemanalLunesMiercolesViernesEnDosSemanas` · `cd src/api && composer test -- --filter testSemanalLunesMiercolesViernesEnDosSemanas` | 2026-11-23 |
| CP-022 | RF-03 | PASS | `src/api/tests/unit/Domain/ReglaRecurrenciaTest.php::testFechaFinAntesDelRango` · `cd src/api && composer test -- --filter testFechaFinAntesDelRango` | 2026-11-23 |
| CP-023 | RF-03 | PASS | `src/api/tests/feature/Tareas/GeneradorTest.php::testGenerarDosVecesNoDuplica` · `cd src/api && composer test -- --filter testGenerarDosVecesNoDuplica` | 2026-11-23 |
| CP-024 | RF-03 | PASS | `src/api/tests/feature/Tareas/AdminTareasTest.php::testEditarSoloCambiaPendientesFuturas` · `cd src/api && composer test -- --filter testEditarSoloCambiaPendientesFuturas` | 2026-11-23 |
| CP-025 | RF-03 | PASS | `src/api/tests/feature/Tareas/AdminTareasTest.php::testDesactivarTarea` · `cd src/api && composer test -- --filter testDesactivarTarea` | 2026-11-23 |
| CP-026 | RF-03 | PASS | `src/api/tests/feature/Tareas/AdminTareasTest.php::testRangoDePuntosPorTipo` · `cd src/api && composer test -- --filter testRangoDePuntosPorTipo` | 2026-11-23 |
| CP-027 | RF-04 | PASS | `src/api/tests/feature/Tareas/MiDiaTest.php::testOrdenaPorHoraYSinHoraAlFinal` · `cd src/api && composer test -- --filter testOrdenaPorHoraYSinHoraAlFinal` | 2026-11-23 |
| CP-028 | RF-04 | PASS | `src/api/tests/feature/Tareas/MiDiaTest.php::testNoVeInstanciasDeOtraHija` · `cd src/api && composer test -- --filter testNoVeInstanciasDeOtraHija` | 2026-11-23 |
| CP-029 | RF-05 | PASS | `src/api/tests/feature/Tareas/MiDiaTest.php::testMarcarHechaHoy` · `cd src/api && composer test -- --filter testMarcarHechaHoy` | 2026-11-23 |
| CP-030 | RF-05 | PASS | `src/api/tests/feature/Tareas/MiDiaTest.php::testMarcarHechaManana` · `cd src/api && composer test -- --filter testMarcarHechaManana` | 2026-11-23 |
| CP-031 | RF-05 | PASS | `src/api/tests/feature/Tareas/MiDiaTest.php::testMarcarHechaDeOtroPerfil` · `cd src/api && composer test -- --filter testMarcarHechaDeOtroPerfil` | 2026-11-23 |
| CP-032 | RF-05 | PASS | `src/web/tests/unit/mi-dia/MiDia.test.tsx` (describe «CP-032 · Tocar "Listo" celebra y llama marcarHecha») · `cd src/web && npm test` → `Test Files 9 passed (9) · Tests 74 passed (74)` | 2026-11-23 |
| CP-033 | RF-13 | PASS | `src/api/tests/feature/Tareas/MiDiaTest.php::testEscolarAparecHastaLaEntregaODesapareceAlAprobarse` · `cd src/api && composer test -- --filter testEscolarAparecHastaLaEntregaODesapareceAlAprobarse` | 2026-11-23 |
| CP-034 | RF-04 | PASS | `src/api/tests/unit/Domain/CalendarioTest.php::testHoyALas2330EnCiudadJuarezUsaLaFechaLocal` · `cd src/api && composer test -- --filter testHoyALas2330EnCiudadJuarezUsaLaFechaLocal` | 2026-11-23 |
| CP-035 | RF-19 | PASS | `src/api/tests/feature/Tareas/TareasPropiasTest.php::testAventureraCreaEscolarPropiaYElServidorFijaLosCampos` · `cd src/api && composer test -- --filter testAventureraCreaEscolarPropiaYElServidorFijaLosCampos` | 2026-11-23 |
| CP-036 | RF-19 | PASS | `src/api/tests/feature/Tareas/TareasPropiasTest.php::testAventureraNoCreaTareaDeCasa` · `cd src/api && composer test -- --filter testAventureraNoCreaTareaDeCasa` | 2026-11-23 |
| CP-037 | RF-19 | PASS | `src/api/tests/feature/Tareas/TareasPropiasTest.php::testExploradoraNoTieneTareasPropias` · `cd src/api && composer test -- --filter testExploradoraNoTieneTareasPropias` | 2026-11-23 |
| CP-038 | RF-19 | MOVIDO AL S3 | La parte del S2 está probada (aparece en `GET /admin/tareas` con `creada_por = perfil`; se marca hecha con 5 puntos y sin movimiento). La aprobación y el crédito único dependen de E-33/E-34 (T-S3-02); se registra en `sprint-03.md` | 2026-11-23 |
| CP-039 | RF-19 | PASS | `src/web/tests/unit/mis-tareas/escolarPropia.test.tsx` (describe «CP-039 · Formulario de escolar propia») · `cd src/web && npm test` → `Test Files 9 passed (9) · Tests 74 passed (74)` | 2026-11-23 |
| CP-SEG-05 | RNF-07 | PASS | `src/api/tests/feature/Tareas/AdminTareasTest.php::testTituloConInyeccionSqlSeGuardaLiteral` · `cd src/api && composer test -- --filter testTituloConInyeccionSqlSeGuardaLiteral` | 2026-11-23 |

Suite completa de `s2/api-tareas` (2026-11-23, tras integrar `main` con el catálogo de íconos y avatares): `cd src/api && composer test` → `OK (219 tests, 1390 assertions)` con PHPUnit 11.5.58, PHP 8.5.12 y MariaDB 10.11.20. Frontend en la misma rama: `cd src/web && npm test` → `Test Files 9 passed (9) · Tests 74 passed (74)`.

## Hallazgos y ajustes

1. **CP-038 no se puede cerrar en el S2.** Su resultado esperado incluye la bandeja de aprobación (E-33) y la acreditación de 5 puntos una sola vez al aprobar (E-34, `PuntosService`), que son de T-S3-02. La parte del S2 está probada; el caso queda PENDIENTE hasta el S3. Decisión: moverlo al S3 o dejar el S2 en 20/21 hasta entonces.
2. **Cobertura del dominio sin medir.** T-S2-01 pide 100 % de cobertura de `App\Domain`, pero el PHP local no tiene driver de cobertura (ni pcov ni xdebug) y la instalación de pcov no se autorizó en esta sesión. Las pruebas de `tests/unit/Domain/` se escribieron para ejercitar cada rama (incluidos todos los errores), pero el 100 % no está medido. Se necesita pcov o xdebug local o en CI para comprobarlo.
3. **"Instancias futuras" = desde hoy, incluida la de hoy.** Editar una tarea borra y regenera las `pendiente` con `fecha >= hoy`; desactivarla borra las `pendiente` con `fecha >= hoy`. Las de días pasados, `hecha`, `aprobada` y `devuelta` no se tocan (CP-024, CP-025).
4. **Escolares: su única instancia es del día de creación.** Al editar una escolar se actualizan también puntos, hora y entrega de su instancia `pendiente` aunque su fecha ya haya pasado, porque sigue vigente hasta la entrega; al desactivarla se borra su instancia `pendiente` sea cual sea la fecha. En "Mi día" el día de creación sigue mostrando la instancia (con su estado, incluso `aprobada`); los días siguientes solo la arrastran mientras no esté aprobada y la tarea siga activa.
5. **Pendiente del S3 en E-12**: el campo `foto` del multipart se ignora y `requiere_foto` no se exige en el servidor (T-S3-01).
6. **Errores de validación de tareas con campos planos** (`titulo`, `perfil_id`, `categoria_id`, `puntos`, `materia`, `fecha_entrega`, `frecuencia`, `dias_semana`, `hora`, `fecha_inicio`, `fecha_fin`, `icono`, `requiere_foto`), los mismos nombres que usa el formulario (`validarTarea.ts`), no `recurrencia.*`.
7. **Entrega de una escolar**: al crear debe ser ≥ hoy y ≥ `fecha_inicio`; al editar, ≥ `fecha_inicio`. En las propias, siempre ≥ hoy (como el formulario). Las escolares del padre deben ser `una_vez` (400 en `frecuencia` si no).
8. **E-18 lista solo las propias activas**; editar o desactivar una propia ya desactivada responde 404. Al editar una propia se conservan sus puntos y su fecha de inicio; `puntos` del cuerpo se ignora.
9. **Cron**: `tareas:programadas` corre "generar" a las 00:05, en la primera ejecución del día (marca en `writable/cache/generacion.ultima`) o con `--forzar`; registra en `writable/logs/cron.log`. `GET /yo/dia` llama a `GeneradorInstancias::asegurar(fecha)`.
10. **Catálogo (cambio de `main`)**: `App\Domain\Catalogo` replica las 52 claves de `iconos.ts` y las 16 de `avatares.ts` (`CatalogoTest` las compara por regex). El servidor valida `icono` en tareas y `avatar` en E-24/E-25. **El doc 08 §5 dice "21 claves" de avatar** (agrega `indigo`, `ambar`, `menta`, `rosa`, `lavanda`), pero `avatares.ts` tiene 16; se siguió el código (16). Corregir uno de los dos.

- RNF-09 / T-S2-01: el 100 % de cobertura de `App\Domain` no se pudo medir en local (PHP sin pcov ni xdebug). Las pruebas de `tests/unit/Domain/` recorren todas las ramas; la medición queda para el pipeline de CI (T-S0-05), con `shivammathur/setup-php` y `coverage: pcov`.
- Contrato del doc 05 congelado el 2026-11-23 al cerrar este sprint (ADR-008).
