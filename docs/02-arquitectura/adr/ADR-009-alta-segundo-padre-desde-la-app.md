# ADR-009 · Alta del segundo padre desde la app (cambio de contrato 1.0 → 1.1)

**Estado:** Aceptada · **Fecha:** 2026-11-23 · **Decide:** David García (lo pidió al usar el entorno de pruebas)

## Contexto

Hasta ahora la única alta por web era la instalación inicial (RF-20); el segundo padre se creaba con `php spark usuarios:crear` por SSH (T-S1-01, doc 04 amenaza A-01). Al usar el entorno de pruebas, David pidió poder dar de alta al otro padre desde la app, sin consola.

El contrato del doc 05 está congelado desde el cierre del S2 (ADR-008); agregar endpoints y métodos a `ApiClient` requiere un ADR de cambio de contrato (Metodología v4 §11).

## Decisión

1. Se agregan dos endpoints de **modo padre** (filtros `sesion` + `modoPadre`):
   - **E-51** `GET /admin/usuarios` → `UsuarioAdmin[]`: los padres de la familia, sin datos sensibles.
   - **E-52** `POST /admin/usuarios` → `UsuarioAdmin` (201): crea un padre con nombre, correo, contraseña y PIN.
2. El máximo sigue siendo **2 padres** (doc 03). Con 2, E-52 responde 409 `ESTADO_INVALIDO`. La comprobación y el alta van en una transacción con `SELECT … FOR UPDATE` sobre `usuarios`, para que dos altas simultáneas no pasen de 2.
3. Las reglas son las mismas que en la instalación y en la consola: nombre de hasta 60, correo válido y único, contraseña de 10 caracteres o más y PIN no trivial. La validación vive en un solo lugar, `UsuariosService`, que usan E-52 y `usuarios:crear`.
4. El padre que da el alta define la contraseña y el PIN y se los comparte al otro. El nuevo padre puede cambiar su PIN en Ajustes (E-48, `pin_padre`). El cambio de contraseña desde la app queda fuera de este ADR (se restablece por consola, como hoy).
5. `ApiClient` agrega `adminUsuarios()` y `crearUsuario(u)`, y los tipos `UsuarioAdmin` y `UsuarioEntrada`. La interfaz sigue congelada en su versión **1.1**.

## Consecuencias

- (+) La familia completa su configuración sin SSH.
- (+) Sin registro público: el alta exige sesión de dispositivo **y** modo padre (PIN vigente de 10 minutos), así que la superficie de A-01 no crece.
- (−) La contraseña inicial viaja del padre que da el alta al nuevo padre fuera de la app (de palabra o por mensaje). Se recomienda cambiar el PIN al entrar.
- (−) Tampoco hay baja de padres desde la app; si hiciera falta, se hace por consola.
