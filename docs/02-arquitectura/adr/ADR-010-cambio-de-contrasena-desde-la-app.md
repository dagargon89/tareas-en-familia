# ADR-010 · Cambio y restablecimiento de contraseña de los padres desde la app (contrato 1.1 → 1.2)

**Estado:** Aceptada · **Fecha:** 2026-11-23 · **Decide:** David García (pidió que los padres puedan cambiar la contraseña desde la app)

## Contexto

Hasta ahora la contraseña de un padre solo se cambiaba por consola (T-S1-01: "se restablece por consola"); ADR-009 dejó el cambio fuera de su alcance. No hay correo de recuperación (la app no envía correos), así que si un padre olvida su contraseña hoy hace falta SSH.

## Decisión

1. Se agrega **E-53** `PUT /admin/usuarios/{id}/password` en **modo padre** (sesión de dispositivo + PIN vigente), cuerpo `{ password, password_actual? }`, respuesta `UsuarioAdmin` del padre (200), como las demás ediciones de la API. No se usa `204`: con el servidor de desarrollo de PHP, el proxy de Vite rechazaba de forma intermitente esa respuesta a un `PUT` (`Parse Error: Data after Connection: close`).
2. **Cambio propio** (`{id}` es el padre de la sesión): exige `password_actual` correcta. La verificación comparte el límite del login (5 intentos por minuto por IP, 429 `DEMASIADOS_INTENTOS`); una actual incorrecta responde 400 `VALIDACION` en `campos.password_actual`.
3. **Restablecimiento del otro padre** (`{id}` es el otro): no pide la actual, para resolver el olvido sin consola. Los dos padres ya tienen el mismo nivel de acceso (ADR-009), así que no se abre un permiso nuevo.
4. La contraseña nueva cumple la regla de siempre (10 caracteres o más) y se guarda con `Hash` (Argon2id). Padre inexistente: 404.
5. `ApiClient` agrega `cambiarPassword(usuarioId, datos)` y el tipo `CambioPasswordEntrada`. La interfaz queda en la versión **1.2**.

## Consecuencias

- (+) Olvidar la contraseña ya no exige SSH: el otro padre la restablece.
- (+) El cambio propio no se puede usar para adivinar la actual más rápido que en el login.
- (−) Las sesiones de dispositivo abiertas con la contraseña anterior siguen vigentes hasta que vencen o se cierran (la sesión es por dispositivo y no guarda la contraseña; ADR-003).
- (−) Con un solo padre registrado, si olvida la contraseña sigue haciendo falta la consola (`php spark usuarios:crear` no aplica; se restablece con SQL o con un comando futuro).
