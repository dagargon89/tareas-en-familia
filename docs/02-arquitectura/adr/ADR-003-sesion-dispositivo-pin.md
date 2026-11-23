# ADR-003 · Sesión de dispositivo con cookie y PIN por perfil, sin JWT

**Estado:** Aceptada · **Fecha:** 2026-11-22 · **Decide:** David García

## Contexto

Las niñas de 6 a 9 años no pueden manejar correo y contraseña. La SPA y la API comparten dominio. La app se usa en uno o pocos dispositivos de la familia.

## Decisión

1. El padre inicia sesión una vez por dispositivo con correo y contraseña. CI4 emite una cookie de sesión `HttpOnly`, `Secure`, `SameSite=Lax`, de 30 días.
2. Cada hija entra eligiendo su avatar y escribiendo un PIN de 4 dígitos. El perfil activo se guarda en la sesión.
3. El modo padre exige el PIN del padre que abrió la sesión del dispositivo y dura 10 minutos deslizantes.
4. Protección CSRF de CI4 con token en header `X-CSRF-TOKEN`.

## Alternativas descartadas

- **JWT en localStorage:** expuesto a XSS y sin beneficio en un solo dominio.
- **Cuentas con contraseña por hija:** fricción alta para primaria.
- **Firebase Auth:** dependencia externa y datos de menores en un tercero.

## Consecuencias

- (+) Sin tokens en JavaScript; revocación inmediata borrando la sesión.
- (−) Un PIN de 4 dígitos es débil por sí solo; se mitiga con bloqueo tras 5 intentos y con que el PIN solo funciona dentro de una sesión de dispositivo ya abierta por el padre.
