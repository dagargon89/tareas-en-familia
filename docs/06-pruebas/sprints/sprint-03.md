# Sprint 03 · Evidencias, aprobación y puntos

Fechas: 2026-11-23 a —

Ramas: `s3/api-evidencias` (T-S3-01, T-S3-02) · UI del S3 ya integrada en `main` (T-S3-03).

| Caso | RF | Estado | Evidencia | Fecha |
|---|---|---|---|---|
| CP-038 | RF-19 | PASS | `src/api/tests/feature/Aprobacion/AprobacionTest.php::testEscolarPropiaHechaYAprobada` · `cd src/api && composer test -- --filter testEscolarPropiaHechaYAprobada` | 2026-11-23 |
| CP-040 | RF-06 | PASS | `src/api/tests/feature/Evidencias/EvidenciasTest.php::testJpegConGpsSeGuardaComoWebpSinExif` · `cd src/api && composer test -- --filter testJpegConGpsSeGuardaComoWebpSinExif` · además, servidor real (`php -S` con `upload_max_filesize=4M`) y `curl -F foto=@…jpg` (2,1 MB con EXIF GPS): 200 y `GET /evidencias/1` → `Content-Type: image/webp`, `Cache-Control: private, max-age=300`, `Web/P image, VP8, 1600x1200`, sin `Exif` | 2026-11-23 |
| CP-041 | RF-06 | PASS | `src/api/tests/feature/Evidencias/EvidenciasTest.php::testArchivoDeCuatroMegasEs400` · `cd src/api && composer test -- --filter testArchivoDeCuatroMegasEs400` | 2026-11-23 |
| CP-042 | RF-06 | PASS | `src/api/tests/feature/Evidencias/EvidenciasTest.php::testPhpRenombradoAJpgEs400` · `cd src/api && composer test -- --filter testPhpRenombradoAJpgEs400` | 2026-11-23 |
| CP-043 | RF-06 | PASS | `src/api/tests/feature/Evidencias/EvidenciasTest.php::testSoloLaDuenaOElPadreVenLaFoto` · `cd src/api && composer test -- --filter testSoloLaDuenaOElPadreVenLaFoto` | 2026-11-23 |
| CP-044 | RF-06 | PASS | `src/api/tests/feature/Evidencias/EvidenciasTest.php::testPurgaDeEvidenciasVencidas` · `cd src/api && composer test -- --filter testPurgaDeEvidenciasVencidas` | 2026-11-23 |
| CP-045 | RF-07 | PASS | `src/api/tests/feature/Aprobacion/AprobacionTest.php::testAprobarInstanciaHecha` · `cd src/api && composer test -- --filter testAprobarInstanciaHecha` | 2026-11-23 |
| CP-046 | RF-07 | PASS | `src/api/tests/feature/Aprobacion/AprobacionTest.php::testAprobarDosVeces` · `cd src/api && composer test -- --filter testAprobarDosVeces` | 2026-11-23 |
| CP-047 | RF-07 | PASS | `src/api/tests/feature/Aprobacion/AprobacionTest.php::testDevolverSinMensaje` · `cd src/api && composer test -- --filter testDevolverSinMensaje` | 2026-11-23 |
| CP-048 | RF-07 | PASS | `src/api/tests/feature/Aprobacion/AprobacionTest.php::testDevolverConMensaje` · `cd src/api && composer test -- --filter testDevolverConMensaje` | 2026-11-23 |
| CP-049 | RF-07 | PASS | `src/api/tests/feature/Aprobacion/AprobacionTest.php::testAprobacionEnLoteDeCinco` · `cd src/api && composer test -- --filter testAprobacionEnLoteDeCinco` | 2026-11-23 |
| CP-050 | RF-08 | PASS | `src/api/tests/unit/Domain/NivelesTest.php::testNivelPorXp` · `cd src/api && composer test -- --filter testNivelPorXp` | 2026-11-23 |
| CP-051 | RF-08 | PASS | `src/api/tests/feature/Aprobacion/AprobacionTest.php::testCanjeAprobadoNoBajaElXp` · `cd src/api && composer test -- --filter testCanjeAprobadoNoBajaElXp` (sin canjes en el S3, el movimiento `canje` negativo se inserta desde la prueba) | 2026-11-23 |
| CP-052 | RF-08 | PASS | `src/api/tests/feature/Aprobacion/AprobacionTest.php::testPuntosEnElCuerpoDeMarcarHechaSeIgnoran` · `cd src/api && composer test -- --filter testPuntosEnElCuerpoDeMarcarHechaSeIgnoran` | 2026-11-23 |

Suite completa de `s3/api-evidencias` (2026-11-23): `cd src/api && composer test` → `OK (260 tests, 1726 assertions)` con PHPUnit 11.5.58, PHP 8.5.12 y MariaDB 10.11.20.

## Hallazgos y ajustes

1. **Token CSRF en respuestas de error (corregido, commit aparte).** El header `X-CSRF-TOKEN` lo ponía un filtro global *after*, que CI4 no ejecuta en respuestas de `ApiException` ni de filtros que cortan la petición. Tras un 400 o 409 de una petición mutante el token ya se había regenerado, así que la siguiente mutación recibía 403 `CSRF` y dependía del reintento del `HttpApiClient`. Ahora `csrfHeader` es filtro requerido y actúa en todo `/api/*` salvo `/api/v1/salud`. Se detectó con la prueba en servidor real.
2. **`upload_max_filesize` del hosting debe ser ≥ 3 MB** (y `post_max_size` mayor). El PHP local trae 2 MB y PHP descarta la foto antes de que llegue a la app; ahora ese caso (`UPLOAD_ERR_INI_SIZE`) responde 400 en `campos.foto` con el mensaje de 3 MB. **Verificar en T-S0-05 / H-01** y, si hace falta, fijarlo con `.user.ini` en `public/`.
3. **`is_uploaded_file()` no se puede ejercitar en PHPUnit** (CLI). `EvidenciasService::subidaValida()` es la única pieza sustituida en `EvidenciasTest`; tamaño, `finfo`, GD y guardado son reales. La ruta completa con `is_uploaded_file()` se verificó a mano contra el servidor real (evidencia de CP-040).
4. **Re-codificación con GD directo** (`imagecreatefromstring` → `imagescale` → `imagewebp` calidad 80) en lugar de la clase `Image` de CI4 que cita el doc 04 §3.5: el resultado es el mismo (GD, sin EXIF) y evita el chequeo de MIME por extensión de CI4. Antes de descartar los metadatos se respeta la orientación EXIF de los JPEG de celular (si la extensión `exif` existe).
5. **Lote (E-35)**: cada instancia se aprueba en su propia transacción. Las que no están `hecha` (pendientes, ya aprobadas, devueltas, inexistentes) y los ids repetidos se omiten sin abortar; la respuesta trae solo las aprobadas, en el orden recibido. `ids` debe ser un arreglo de 1 a 100 enteros (400 en `campos.ids`).
6. **Foto opcional**: si la tarea no exige foto pero llega una, se guarda igual (doc 03: "foto opcional, una por instancia"). Al re-marcar una `devuelta` con foto nueva, la fila se actualiza y el archivo viejo se borra tras el commit.
7. **`borrar_despues` = fecha de aprobación + `evidencia_dias_retencion`** y la purga borra cuando `borrar_despues < hoy` (el día que marca todavía se conserva).
8. **Progreso**: `disponible = max(0, saldo − reservado)`. `semana_actual.porcentaje` va con 2 decimales y sale entero cuando no tiene parte decimal (`0`, `100`). `racha_semanas` cuenta las semanas cerradas (anteriores a la actual) con `cumplida = 1` desde la más reciente hasta la primera no cumplida, saltando las que no tienen registro. `meta_familiar.progreso_puntos` suma los movimientos `tarea` de todas las hijas desde las 00:00 de `fecha_inicio`.
9. **Punto de extensión del S4**: `AprobacionService::alAprobar()` corre dentro de la transacción de aprobar y hoy devuelve `[[], false]` (`insignias_nuevas: []`, `meta_cumplida: false`).
10. **Prueba del S2 ajustada**: `MiDiaTest::testFotoEnElMultipartSeIgnoraEnElS2` pasó a `testCampoFotoDeTextoNoCuentaComoFoto` porque desde el S3 la foto es obligatoria cuando la tarea la pide.
