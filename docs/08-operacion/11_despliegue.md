# 11 · Despliegue y operación

**Proyecto:** Tareas en Familia · **Versión:** 0.2 (subdominio de pruebas activo, T-S0-05) · **Fecha:** 2026-11-23

Esta versión cubre el despliegue automático al **subdominio de pruebas**. La Fase 3 la completa con producción, respaldos y la prueba de restauración.

## 1. Cómo funciona

```text
push a main ──► CI (.github/workflows/ci.yml)
                 api · web · e2e  ──► paquete (release.tar.gz + deploy_remoto.sh)
                                          │
                 Despliegue (.github/workflows/deploy.yml)  ◄─┘  solo si el CI pasó
                 scp ──► servidor: deploy_remoto.sh desplegar
                 curl https://<pruebas>/api/v1/salud y /app/  ──► si falla: revertir
```

En el servidor (doc 02 §6), con `DEPLOY_BASE` fuera de la carpeta pública:

| Ruta | Contenido |
|---|---|
| `DEPLOY_BASE/releases/<AAAAMMDD-HHMMSS>/` | Código de cada release (se conservan los 5 más recientes) |
| `DEPLOY_BASE/app-tareas` | Enlace al release activo (lo usan el cron y la CLI) |
| `DEPLOY_BASE/compartido/.env` | Secretos del entorno; se crea a mano una vez (§3) |
| `DEPLOY_BASE/compartido/writable/` | Sesiones, logs, caché, evidencias y respaldos; persisten entre releases |
| `DEPLOY_BASE/compartido/subidas/` | Paquete recibido y copia de `deploy_remoto.sh` (para revertir) |
| `DEPLOY_PUBLICO` (raíz web del subdominio) | Copia de `public/` del release activo. `index.php` apunta a la ruta real del release |

`deploy_remoto.sh desplegar`:

1. Descomprime el paquete en un release nuevo y enlaza `.env` y `writable/` de `compartido/`.
2. Corre `php spark tareas:migrar` (migraciones y catálogos). Si falla, borra el release y **no cambia nada**.
3. Publica `public/` en la raíz web sin borrar lo ajeno (`.well-known` del certificado) y cambia el enlace `app-tareas` de forma atómica.
4. Borra los releases viejos (siempre conserva el activo y el anterior).

Las migraciones no se revierten: cada migración debe ser compatible con el release anterior para que `revertir` sea seguro.

## 2. Entorno de pruebas actual (`task.scalegridtech.com`)

| Dato | Valor |
|---|---|
| URL | `https://task.scalegridtech.com` (SSL de Let's Encrypt) |
| SSH | `u575426443@82.180.175.69`, puerto `65002` (servidor `us-phx-web753`) |
| `DEPLOY_BASE` | `/home/u575426443/tareas-pruebas` |
| `DEPLOY_PUBLICO` | `/home/u575426443/domains/scalegridtech.com/public_html/task` |
| `DEPLOY_PHP` | `/opt/alt/php83/usr/bin/php` (**obligatorio**: el `php` de la terminal es 8.2) |
| Base de datos | `u575426443_tareas` (usuario `u575426443_tareas`), MariaDB 11.8.9, `localhost:3306` |
| Cron | Uid `ablqOI2l8C`, cada minuto: `/opt/alt/php83/usr/bin/php /home/u575426443/tareas-pruebas/app-tareas/spark tareas:programadas` |
| `.env` del servidor | `DEPLOY_BASE/compartido/.env` (600). Copia local fuera del repositorio: `~/.config/tareas-en-familia/pruebas.env` |

Particularidades de Hostinger (CloudLinux con CageFS) encontradas al activar el entorno:

- El `php` de la terminal es 8.2; el sitio corre 8.3.37. Toda la CLI (despliegue y cron) usa `/opt/alt/php83/usr/bin/php`.
- No existe `/dev/fd`: los scripts no pueden usar sustitución de procesos (`<(…)`).
- En la web, PHP deshabilita `exec`, `shell_exec`, `proc_open` y `symlink`; en la CLI están disponibles (los respaldos de la Fase 3 van por cron).
- LiteSpeed agrega un segundo encabezado `Content-Security-Policy: upgrade-insecure-requests`; se combina con el del doc 04 sin conflicto.
- La raíz del subdominio trae un `default.php` de relleno; `.htaccess` fija `DirectoryIndex index.php` para que no le gane a la app.
- **El CDN de Hostinger está apagado para este subdominio (2026-11-23) y debe seguir así.** Con el CDN, el primer byte tardaba de 2 a 20 s incluso en `robots.txt` (el propio CDN reportaba esa espera en `x-hcdn-upstream-rt`), mientras el servidor respondía en 12–36 ms por dentro y en 80–100 ms directo por internet. Sin CDN: 80–105 ms en `/api/v1/salud`, `/api/v1/csrf`, `/app/` y estáticos, y la pantalla de inicio lista en 0,8–1,1 s con caché vacía. LiteSpeed ya comprime con Brotli y da caché de 7 días a los assets con hash; el certificado de Hostinger y HSTS siguen activos. Se cambia con el conector de Hostinger (`hosting_websites_update-settings`, `cdn`) o en hPanel. `scalegridtech.com` y `crm.scalegridtech.com` conservan su CDN.

## 3. Configuración única en GitHub

En **Settings → Environments → New environment → `pruebas`**:

| Tipo | Nombre | Valor |
|---|---|---|
| Secret | `SSH_KEY` | Llave privada de despliegue (abajo) |
| Secret | `SSH_KNOWN_HOSTS` | Salida de `ssh-keyscan -p <puerto> <host>` |
| Variable | `SSH_HOST` | Host SSH de hPanel (H-04) |
| Variable | `SSH_PORT` | Puerto SSH de hPanel (Hostinger suele usar `65002`) |
| Variable | `SSH_USER` | Usuario SSH (`u123456789`) |
| Variable | `DEPLOY_BASE` | Carpeta fuera de la raíz web, p. ej. `/home/u123456789/tareas-pruebas` |
| Variable | `DEPLOY_PUBLICO` | Raíz web del subdominio (H-03), p. ej. `/home/u123456789/domains/ejemplo.com/public_html/pruebas` |
| Variable | `DEPLOY_PHP` | PHP 8.3 de la CLI si `php` no lo es, p. ej. `/usr/bin/php8.3` (opcional) |
| Variable | `URL_PRUEBAS` | `https://pruebas.ejemplo.com` |

Llave de despliegue (en tu computadora; la privada solo va al secret):

```bash
ssh-keygen -t ed25519 -f despliegue_tareas -N "" -C "github-actions tareas-en-familia"
```

La pública (`despliegue_tareas.pub`) se agrega en **hPanel → Avanzado → Acceso SSH → Llaves SSH**. Mientras falte alguna variable o secret, el workflow termina con el aviso "Despliegue no configurado" y no hace nada. `SSH_KNOWN_HOSTS` se obtiene con `ssh-keyscan -p 65002 82.180.175.69`; huellas esperadas: ED25519 `SHA256:hvyf5Si5PjvmwKSG+jMrupxuc2vvXBN+Jcnmt1VlJlA`, ECDSA `SHA256:7ylP9YilJoDPkuqvPk8AeDQN5aVdH5ZINRPeOF+hG2s`, RSA `SHA256:TPdHOwWeOL4I0MdQ8hgn5P1mQe9SgVeXQqhfp9j/tss`.

## 4. Configuración única en el servidor

1. **hPanel → Dominios → Subdominios**: crear el subdominio de pruebas (H-03) y anotar su carpeta (`DEPLOY_PUBLICO`). Activar SSL.
2. **hPanel → Avanzado → Configuración de PHP**: PHP 8.3 para el sitio (H-01). Por SSH, confirmar la CLI con `php -v` (o la ruta de `DEPLOY_PHP`).
3. **hPanel → Bases de datos**: crear la base y su usuario. Confirmar la versión con `SELECT VERSION();` (H-02).
4. Por SSH: verificar GD con WebP, `exec()` y `mysqldump` (H-07, H-08):

   ```bash
   php -r 'print_r(gd_info());' | grep -i webp
   php -r 'var_dump(function_exists("exec"));'
   which mysqldump
   ```

5. Por SSH: crear `DEPLOY_BASE/compartido/.env` (permisos `600`) con esta plantilla:

   ```ini
   CI_ENVIRONMENT = production
   app.baseURL = 'https://pruebas.ejemplo.com/'
   database.default.hostname = localhost
   database.default.database = u123456789_tareas_pruebas
   database.default.username = u123456789_tareas
   database.default.password = <contraseña de la base>
   database.default.DBDriver = MySQLi
   database.default.port = 3306
   cookie.secure = true
   encryption.key = hex2bin:<php -r 'echo bin2hex(random_bytes(32));'>
   instalacion.token = <php -r 'echo bin2hex(random_bytes(24));'>
   ```

6. **hPanel → Avanzado → Cron Jobs**, cada minuto (ADR-005):

   ```text
   cd DEPLOY_BASE/app-tareas && php spark tareas:programadas >/dev/null 2>&1
   ```

   El comando escribe su bitácora en `compartido/writable/logs/cron.log`.

7. Tras el primer despliegue, abrir `https://pruebas.ejemplo.com/app/`: pedirá "Configura la app" con el `instalacion.token` (RF-20).

## 5. Operación

| Tarea | Cómo |
|---|---|
| Desplegar | Automático en cada push a `main` con el CI en verde. A mano: Actions → Despliegue → Run workflow → `desplegar` |
| Revertir al release anterior | Actions → Despliegue → Run workflow → `revertir` (o por SSH: `bash DEPLOY_BASE/compartido/subidas/deploy_remoto.sh revertir --base DEPLOY_BASE --publico DEPLOY_PUBLICO`) |
| Ver el release activo | `bash DEPLOY_BASE/compartido/subidas/deploy_remoto.sh estado --base DEPLOY_BASE` |
| Probar el despliegue en local | `scripts/empaquetar.sh /tmp/r.tar.gz` y `scripts/deploy_remoto.sh desplegar --base <carpeta> --paquete /tmp/r.tar.gz` |

## 6. Verificación en el subdominio (cierra CP-SEG-08 y CP-NF-05)

```bash
U=https://pruebas.ejemplo.com
curl -s -o /dev/null -w '%{http_code}\n' $U/.env              # 403
curl -s -o /dev/null -w '%{http_code}\n' $U/writable/          # 403 o 404
curl -s -o /dev/null -w '%{http_code}\n' $U/.htaccess          # 403
curl -sI $U/app/ | grep -i strict-transport-security           # HSTS presente
curl -sI $U/api/v1/salud | grep -iE 'content-security|x-frame' # headers del doc 04
```

CP-NF-05: abrir `$U/app/` en Android (Chrome → Instalar app) y en iPhone (Safari → Compartir → Agregar a inicio) y confirmar que abre sin barra del navegador.
