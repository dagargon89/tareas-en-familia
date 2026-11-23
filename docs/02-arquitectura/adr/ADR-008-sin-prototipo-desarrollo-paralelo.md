# ADR-008 · Sin prototipo de Fase 1; frontend y backend en paralelo por ramas

**Estado:** Aceptada · **Fecha:** 2026-11-22 · **Decide:** David García

## Contexto

La Fase 1 planeaba un prototipo navegable con `MockApiClient` antes de construir la API. David prefiere ver la interfaz real crecer mientras se construye el backend, sin mantener un mock que luego se descarta.

## Decisión

1. La Fase 1 se reduce a T-F1-01 (proyecto web con tokens, ya hecho). Se omiten T-F1-02 a T-F1-06: no hay `MockApiClient`, prototipo ni doc 09.
2. La Fase 2 arranca de inmediato. Cada sprint se divide en una rama de API (`sNN/api-*`) y una de web (`sNN/web-*`) que avanzan en paralelo y se integran a `main` al cerrar sus fichas.
3. Mientras la API de una pantalla no existe, la interfaz se ve en la **galería de componentes** (`/app/galeria`, solo en desarrollo), con datos fijos escritos en el propio archivo de la galería. No es un cliente alternativo y nunca implementa `ApiClient`.
4. El contrato del doc 05 sigue en borrador y se **congela al cerrar el Sprint 2**, cuando las pantallas principales ya lo consumen contra la API real.
5. La sesión con las hijas (doc 06 §5) se hace al final de cada sprint con UI, sobre el subdominio de pruebas o el entorno local.
6. Las pruebas de componentes usan dobles por prueba (`vi.fn()` con la forma de `ApiClient`), no un mock global.

## Consecuencias

- (+) No se escribe código desechable; lo que se ve ya es la app.
- (+) Backend y frontend avanzan a la vez.
- (−) Las primeras pantallas no son navegables de punta a punta hasta que su API esté integrada.
- (−) Sin validación temprana con las hijas: el riesgo de UX se mitiga con la sesión al cierre del S1 y del S2.
