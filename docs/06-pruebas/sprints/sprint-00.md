# Sprint 00 · Base técnica y despliegue a pruebas

Fechas: 2026-11-22 a —

Ramas: `s0/api-base` (T-S0-01 a T-S0-03) · `s0/web-base` (T-S0-04 y base de UI) · T-S0-05 bloqueada por H-03 y H-04.

| Caso | RF | Estado | Evidencia | Fecha |
|---|---|---|---|---|
| CP-SEG-04 | RNF-07 | PASS | `src/api/tests/feature/Seguridad/HeadersTest.php` (200, 404 API, 404 HTML, 3xx, 403 CSRF; valores leídos del doc 04 §3.4; HSTS solo en producción) y `tests/unit/Seguridad/ArchivosPublicosTest.php` (`.htaccess`) · `cd src/api && composer test -- --filter 'HeadersTest\|ArchivosPublicosTest'` | 2026-11-22 |
| CP-SEG-06 | RNF-07 | PASS | `src/api/tests/unit/Seguridad/HashTest.php` (`App\Libraries\Hash`: `$argon2id$`, respaldo `$2y$12$`) y `tests/feature/SemillasTest.php::testHashesGuardadosSonArgon2idOBcrypt` (contraseña y PIN guardados) · `cd src/api && composer test -- --filter 'HashTest\|SemillasTest'` | 2026-11-22 |

## Entorno local

- PHP 8.5 (CLI de Homebrew) con `config.platform.php = 8.3` en Composer para no usar nada que el hosting no tenga.
- MariaDB 10.11.20 local en `127.0.0.1:3307`, bases `tareas_dev` y `tareas_test`.

## Hallazgos y ajustes

Suite completa de la rama `s0/api-base` (2026-11-22): `cd src/api && composer test` → `OK (49 tests, 444 assertions)` con PHPUnit 11.5.58 sobre PHP 8.5.12 y MariaDB 10.11.20 (`tareas_test`).

| Ficha | Estado | Evidencia |
|---|---|---|
| T-S0-01 | Hecha | `tests/feature/Api/SistemaTest.php` (E-01, E-02, 404 `NO_ENCONTRADO`), `tests/unit/Libraries/ErrorApiTest.php` |
| T-S0-02 | Hecha | `tests/feature/EsquemaTest.php` (134 columnas, PK, únicas y 16 FKs contra `03_esquema.json`; índices, motor, CHECK, vista y `activa_unica` contra `03_esquema.sql`), `tests/feature/SemillasTest.php` |
| T-S0-03 | Hecha | CP-SEG-04 y CP-SEG-06, `tests/feature/Seguridad/CsrfTest.php`, `tests/unit/Seguridad/SesionConfigTest.php` |

1. **`.gitignore` de la raíz no re-incluye los `.gitkeep` de `writable/`.** `src/api/writable/*` excluye los directorios y Git no puede re-incluir archivos dentro de un directorio excluido, así que `!src/api/writable/**/.gitkeep` no tiene efecto. Se versionaron con `git add -f` (`cache`, `logs`, `session`, `uploads`, `evidencias`, `respaldos`, más `writable/.htaccess` e `index.html`); una vez rastreados se conservan. Arreglo propuesto al integrar (no se tocó para no chocar con `s0/web-base`): `src/api/writable/*/*` + `!src/api/writable/*/.gitkeep`.
2. **Doc 02 §7 usa `app.cookieSecure`**, que ya no existe en CI4 4.7: la variable es `cookie.secure` (`Config\Cookie`). `Cookie::$secure` vale `true` solo con `CI_ENVIRONMENT = production` y `.env` puede sobrescribirlo. Corregir el doc 02.
3. **Error 500 sin código en el doc 05.** Los errores no controlados en `/api/*` responden `{ error: { codigo: "ERROR_INTERNO", mensaje } }` sin traza (la traza va a `writable/logs`); otros 4xx no previstos usan `ERROR`. Propuesta: agregar `500 ERROR_INTERNO` a la tabla de códigos del doc 05 §1.
4. **`03_esquema.json` no describe índices no únicos, la vista, los CHECK `json_valid` ni el motor/colación.** `EsquemaTest` los compara contra `03_esquema.sql` (lo lee del repo). El JSON se generó en MariaDB 10.11.14 y coincide con 10.11.20.
5. **README de `docs/` (arranque local) siembra solo `DemoSeeder`.** Para que funcione igual que el comando de `CLAUDE.md`, `DemoSeeder` llama a `CatalogoSeeder` si `categorias` está vacía; `CatalogoSeeder` es idempotente (`INSERT IGNORE`).
6. **CSRF con token en sesión y regeneración tras cada petición mutante** (doc 05 §1). Dos peticiones mutantes en paralelo: la segunda recibe `403 CSRF`; el `HttpApiClient` debe tomar el token nuevo del header `X-CSRF-TOKEN` de cada respuesta y reintentar una vez tras `GET /csrf`, o serializar las mutaciones. `GET /salud` queda fuera del filtro para no abrir sesión.
7. **HSTS en `.htaccess` se condiciona a HTTPS** (`expr=%{HTTPS} == 'on'`), no al entorno: en el subdominio de pruebas con HTTPS los archivos estáticos lo enviarán aunque CI4 no esté en producción. Verificar en T-S0-05 que LiteSpeed aplica `Header always set` (CP-SEG-08).
8. **PHPUnit 11**: `codeigniter4/appstarter` trae PHPUnit 10; se subió a `^11.5` según el stack de `CLAUDE.md`. `composer.json` fija `config.platform.php = 8.3.0`.
9. **Raíz `/`** redirige a `/app/` (SPA). Fuera de `/api/*`, un 404 muestra la vista HTML de CI4 (con los mismos headers).

