# 04 · Seguridad y privacidad

**Proyecto:** Tareas en Familia · **Versión:** 1.0 · **Fecha:** 2026-11-22

## 1. Datos que se tratan

| Dato | Sensibilidad | Dónde | Protección |
|---|---|---|---|
| Correo y nombre del padre | Media | `usuarios` | Acceso solo autenticado |
| Contraseña y PIN | Alta | `usuarios`, `perfiles` | Argon2id, nunca en claro ni en logs |
| Apodo y avatar de las hijas | Media (menores) | `perfiles` | No se piden nombre completo, edad, escuela ni ubicación |
| Tareas, puntos, rachas | Baja | Tablas de juego | Acceso por perfil y modo padre |
| Fotos de evidencia | Alta (imágenes de menores) | `writable/evidencias/` | Fuera de la web, sin EXIF, borrado a 30 días |

Principio: **minimizar.** Si un dato no es indispensable para una función del SRS, no se guarda.

## 2. Modelo de amenazas

| ID | Amenaza | Actor | Impacto | Control |
|---|---|---|---|---|
| A-01 | Alguien en internet entra a la app | Externo | Alto | Login con contraseña, throttling, HTTPS, sin registro público |
| A-02 | Una hija entra a modo padre | Interno | Medio | PIN de padre distinto, Filter `modoPadre`, expiración de 10 min |
| A-03 | Una hija entra al perfil de su hermana | Interno | Bajo | PIN por perfil y bloqueo tras 5 intentos |
| A-04 | Una hija se suma puntos manipulando peticiones | Interno | Medio | Puntos solo se acreditan en el servidor al aprobar (RN-03); la API ignora puntos enviados por el cliente |
| A-05 | Robo de cookie por XSS | Externo | Alto | Cookie `HttpOnly`, CSP sin `unsafe-inline` para scripts, React sin `dangerouslySetInnerHTML` |
| A-06 | CSRF desde otro sitio | Externo | Alto | Filter CSRF de CI4 + `SameSite=Lax` |
| A-07 | Acceso directo a fotos por URL | Externo | Alto | Fotos fuera de `public_html`, servidas por controlador con verificación de dueña |
| A-08 | Subida de archivo malicioso | Interno | Medio | Validación de MIME real (`finfo`), máximo 3 MB, re-codificación con GD, nombre aleatorio |
| A-09 | Inyección SQL | Externo | Alto | Query Builder con binding; prohibido concatenar entradas en SQL |
| A-10 | Fuga de `.env` o código | Externo | Alto | Código y `.env` fuera de `public_html`; `.htaccess` niega archivos ocultos |
| A-11 | Indexación en buscadores | Externo | Medio | `robots.txt` con `Disallow: /` y header `X-Robots-Tag: noindex` |
| A-12 | Pérdida de datos | Accidente | Medio | Respaldo diario en el hosting y copia semanal fuera del hosting (descarga por SSH desde GitHub Actions programado; se define en el doc 11) |

## 3. Controles por capa

### 3.1 Autenticación

| Control | Valor |
|---|---|
| Hash | `password_hash($x, PASSWORD_ARGON2ID)`; si el PHP del hosting no tiene Argon2, `PASSWORD_BCRYPT` costo 12 (se verifica en H-01) |
| Contraseña del padre | Mínimo 10 caracteres |
| PIN | 4 dígitos; se rechazan `0000`, `1234`, `1111` a `9999` repetidos y secuencias |
| Bloqueo de PIN | 5 intentos fallidos → `bloqueado_hasta = ahora + 15 min` |
| Throttling de login | 5 intentos por minuto por IP con `Throttler` de CI4 |
| Sesión | `FileHandler`, `sessionExpiration = 2592000` (30 días), `sessionRegenerateDestroy = true`, regenerar id al iniciar sesión y al entrar a modo padre |
| Cookie | `HttpOnly`, `Secure` (producción), `SameSite=Lax`, nombre `tf_sesion` |

### 3.2 Autorización

| Filter | Aplica a | Regla |
|---|---|---|
| `sesion` | Todo `/api/v1/*` excepto `POST /auth/login`, `GET /csrf`, `GET /salud` | Debe existir `usuario_id` en sesión |
| `perfilActivo` | `/api/v1/yo/*` | Debe existir `perfil_activo_id` y el perfil debe estar activo |
| `modoPadre` | Rutas de administración (doc 05) | `modo_padre_hasta` mayor a ahora; renueva 10 min |
| Propiedad | Services | Todo acceso a instancia, evidencia o tarea propia compara `perfil_id` con el perfil activo |

### 3.3 Entrada y salida

- Reglas de validación de CI4 en cada endpoint de escritura; los mensajes de error devuelven el campo, no la regla interna.
- Respuestas JSON con `Content-Type: application/json; charset=utf-8`.
- Errores en producción sin traza: `{ "error": { "codigo": "...", "mensaje": "..." } }`; la traza va a `writable/logs`.

### 3.4 Headers (Filter `securityHeaders` + `.htaccess`)

```text
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: default-src 'self'; img-src 'self' blob: data:; media-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: same-origin
Permissions-Policy: camera=(self), microphone=(), geolocation=()
X-Robots-Tag: noindex, nofollow
```

`style-src 'unsafe-inline'` se mantiene porque Motion aplica estilos en línea; no afecta la ejecución de scripts.

### 3.5 Archivos de evidencia

1. Se valida `$file->isValid()`, tamaño máximo 3 MB y MIME real en (`image/jpeg`, `image/png`, `image/webp`).
2. Se re-codifica con la librería Image de CI4 (GD) a WebP calidad 80, máximo 1600 px por lado. La re-codificación elimina EXIF y GPS.
3. Se guarda como `writable/evidencias/<perfil_id>/<uuid>.webp`.
4. Se sirve con `GET /evidencias/{id}` solo a la dueña o en modo padre, con `Cache-Control: private, max-age=300`.

### 3.6 Secretos

| Secreto | Dónde vive | Nunca en |
|---|---|---|
| Credenciales de base | `.env` del servidor (`compartido/.env`) | Repositorio, logs |
| `encryption.key` | `.env` del servidor | Repositorio |
| Llave SSH de despliegue | Secret `SSH_KEY` de GitHub Actions | Repositorio |
| Datos de conexión SSH | Secrets `SSH_HOST`, `SSH_USER`, `SSH_PORT` | Repositorio |

`.gitignore` incluye `.env`, `writable/*` (salvo `.gitkeep`) y `public/app/`.

## 4. Privacidad de menores

- Sin servicios de terceros en tiempo de ejecución: fuentes, íconos, sonidos y avatares se sirven desde el propio dominio.
- Sin analytics, pixeles ni crash reporting externo.
- La cámara solo se pide al tocar "Agregar foto"; la app funciona sin permiso de cámara.
- El padre puede desactivar un perfil (se oculta y conserva su historial) o borrarlo definitivamente: `PerfilesService::borrar()` elimina en una transacción sus fotos, evidencias, instancias, tareas, movimientos, canjes, insignias y rachas.
- Las fotos se borran a los 30 días de aprobada la tarea.

## 5. Verificación

Cada control tiene al menos un caso `CP-SEG-*` en el doc 06. Antes de cerrar Fase 3 se ejecuta una revisión con la checklist OWASP ASVS nivel 1 y se registra el resultado en el doc 11.
