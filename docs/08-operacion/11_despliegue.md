# 11 · Despliegue y operación

**Proyecto:** Tareas en Familia · **Versión:** 0.1 (subdominio de pruebas, T-S0-05) · **Fecha:** 2026-11-23

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

## 2. Configuración única en GitHub

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

La pública (`despliegue_tareas.pub`) se agrega en **hPanel → Avanzado → Acceso SSH → Llaves SSH**. Mientras falte alguna variable o secret, el workflow termina con el aviso "Despliegue no configurado" y no hace nada.

## 3. Configuración única en el servidor

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

## 4. Operación

| Tarea | Cómo |
|---|---|
| Desplegar | Automático en cada push a `main` con el CI en verde. A mano: Actions → Despliegue → Run workflow → `desplegar` |
| Revertir al release anterior | Actions → Despliegue → Run workflow → `revertir` (o por SSH: `bash DEPLOY_BASE/compartido/subidas/deploy_remoto.sh revertir --base DEPLOY_BASE --publico DEPLOY_PUBLICO`) |
| Ver el release activo | `bash DEPLOY_BASE/compartido/subidas/deploy_remoto.sh estado --base DEPLOY_BASE` |
| Probar el despliegue en local | `scripts/empaquetar.sh /tmp/r.tar.gz` y `scripts/deploy_remoto.sh desplegar --base <carpeta> --paquete /tmp/r.tar.gz` |

## 5. Verificación en el subdominio (cierra CP-SEG-08 y CP-NF-05)

```bash
U=https://pruebas.ejemplo.com
curl -s -o /dev/null -w '%{http_code}\n' $U/.env              # 403
curl -s -o /dev/null -w '%{http_code}\n' $U/writable/          # 403 o 404
curl -s -o /dev/null -w '%{http_code}\n' $U/.htaccess          # 403
curl -sI $U/app/ | grep -i strict-transport-security           # HSTS presente
curl -sI $U/api/v1/salud | grep -iE 'content-security|x-frame' # headers del doc 04
```

CP-NF-05: abrir `$U/app/` en Android (Chrome → Instalar app) y en iPhone (Safari → Compartir → Agregar a inicio) y confirmar que abre sin barra del navegador.
