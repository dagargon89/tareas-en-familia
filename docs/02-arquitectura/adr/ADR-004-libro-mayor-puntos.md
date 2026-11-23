# ADR-004 · Libro mayor de puntos con XP y saldo separados

**Estado:** Aceptada · **Fecha:** 2026-11-22 · **Decide:** David García

## Contexto

Los puntos sirven para dos cosas: subir de nivel y canjear premios. Si canjear bajara el nivel, la niña sentiría que pierde progreso, lo que contradice la regla "nunca castigar".

## Decisión

- Todos los cambios de puntos son filas inmutables en `movimientos_puntos` con signo.
- **XP** = suma de movimientos positivos de tareas e insignias. Nunca baja y define el nivel.
- **Saldo** = suma de todos los movimientos. Los canjes aprobados restan saldo, no XP.
- Índice único (`origen`, `origen_id`) para que una instancia o un canje no se acrediten dos veces.

## Consecuencias

- (+) Auditable: cualquier saldo se explica fila por fila.
- (+) Idempotente ante dobles clics o reintentos.
- (−) El saldo se calcula con una suma; con el volumen de una familia (menos de 10 000 filas al año) no hay impacto.
