# 10 · Registro de pruebas por sprint (índice)

**Proyecto:** Tareas en Familia · Se llena durante la Fase 2.

| Sprint | Archivo | Casos | PASS | Estado |
|---|---|---|---|---|
| S0 | `sprint-00.md` | 2 | 2 | 🟡 En curso (falta T-S0-04 y T-S0-05) |
| S1 | `sprint-01.md` | 20 | 20 | 🟢 Completo (S0 sigue abierto por T-S0-04/05) |
| S2 | `sprint-02.md` | 21 | 0 | ⬜ No iniciado |
| S3 | `sprint-03.md` | 13 | 0 | ⬜ No iniciado |
| S4 | `sprint-04.md` | 14 | 0 | ⬜ No iniciado |
| S5 | `sprint-05.md` | 15 | 0 | ⬜ No iniciado |

Gate: un sprint no arranca hasta que el anterior esté en 🟢 (100 % PASS).

## Formato de cada `sprint-NN.md`

```markdown
# Sprint NN · <objetivo>

Fechas: AAAA-MM-DD a AAAA-MM-DD

| Caso | RF | Estado | Evidencia | Fecha |
|---|---|---|---|---|
| CP-001 | RF-02 | PASS | tests/feature/AuthTest.php::testLoginValido · `composer test -- --filter AuthTest` | AAAA-MM-DD |

## Sesión con las hijas (sprints de UI)

## Hallazgos y ajustes
```
