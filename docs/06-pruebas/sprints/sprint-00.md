# Sprint 00 · Base técnica y despliegue a pruebas

Fechas: 2026-11-22 a —

Ramas: `s0/api-base` (T-S0-01 a T-S0-03) · `s0/web-base` (T-S0-04 y base de UI) · T-S0-05 bloqueada por H-03 y H-04.

| Caso | RF | Estado | Evidencia | Fecha |
|---|---|---|---|---|
| CP-SEG-04 | RNF-07 | PENDIENTE | — | — |
| CP-SEG-06 | RNF-07 | PENDIENTE | — | — |

## Entorno local

- PHP 8.5 (CLI de Homebrew) con `config.platform.php = 8.3` en Composer para no usar nada que el hosting no tenga.
- MariaDB 10.11.20 local en `127.0.0.1:3307`, bases `tareas_dev` y `tareas_test`.

## Hallazgos y ajustes
