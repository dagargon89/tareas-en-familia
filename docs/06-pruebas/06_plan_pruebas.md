# 06 · Plan de pruebas

**Proyecto:** Tareas en Familia · **Versión:** 1.0 · **Fecha:** 2026-11-22

## 1. Estrategia

| Nivel | Herramienta | Ubicación | Corre en |
|---|---|---|---|
| Unitario backend | PHPUnit 11 + `CIUnitTestCase` | `src/api/tests/unit/` | Local y CI |
| Integración API | `FeatureTestTrait` + `DatabaseTestTrait` (MariaDB de prueba) | `src/api/tests/feature/` | Local y CI |
| Unitario frontend | Vitest 3 + Testing Library + dobles de `ApiClient` por prueba (ADR-008) | `src/web/tests/unit/` | Local y CI |
| Extremo a extremo | Playwright (viewports 768×1024 y 360×780) | `src/web/tests/e2e/` | Local y CI contra `php spark serve` |
| Accesibilidad | `@axe-core/playwright` | Dentro de e2e | CI |
| Manual con usuarias | Guion de sesión con las hijas | `06-pruebas/sprints/` | Fin de sprint de UI |

**Gate (Metodología v4):** el sprint N+1 no arranca si el sprint N no tiene el 100 % de sus casos en `PASS` en el doc 10 (`06-pruebas/sprints/`), cada uno con evidencia (ruta del test o comando y salida).

**Cobertura mínima:** 85 % de líneas en `app/Services` y `app/Domain` (RNF-09).

## 2. Catálogo de casos

Columna **Tipo**: U unitario backend · I integración API · F unitario frontend · E extremo a extremo · M manual.

### 2.1 Acceso (RF-01, RF-02, RF-17)

| ID | RF | Tipo | Caso | Resultado esperado |
|---|---|---|---|---|
| CP-001 | RF-02 | I | Login con credenciales válidas | 200, cookie `tf_sesion`, `Sesion.usuario` lleno |
| CP-002 | RF-02 | I | Login con contraseña incorrecta | 401 `CREDENCIALES` |
| CP-003 | RF-01 | I | `GET /perfiles` sin sesión | 401 `SIN_SESION` |
| CP-004 | RF-01 | I | Entrar a perfil con PIN correcto | 200, `perfil_activo` lleno, intentos en 0 |
| CP-005 | RF-01 | I | 5 PIN incorrectos seguidos | Quinto responde 423 `BLOQUEADO` con `bloqueado_hasta` a 15 min |
| CP-006 | RF-01 | I | PIN correcto durante bloqueo | 423 `BLOQUEADO` |
| CP-007 | RF-02 | I | Entrar a modo padre con PIN de padre | 200, `modo_padre_hasta` a 10 min |
| CP-008 | RF-02 | U | Expiración de modo padre tras 10 min sin actividad | `ModoPadreFilter` devuelve 403 `MODO_PADRE_REQUERIDO` |
| CP-009 | RF-01 | E | Hija elige avatar, escribe PIN y llega a "Mi día" | Pantalla "Mi día" con su nombre |
| CP-010 | RF-17 | I | Crear perfil con PIN trivial `1234` | 400 `VALIDACION` en `pin` |
| CP-011 | RF-17 | I | Desbloquear perfil bloqueado | `bloqueado_hasta` null, intentos en 0 |
| CP-012 | RF-17 | I | Borrado definitivo de perfil | Sin filas del perfil en ninguna tabla; fotos borradas |
| CP-013 | RF-20 | I | `GET /instalacion` con 0 usuarios y con 1 usuario | `requerida: true` y luego `requerida: false` |
| CP-014 | RF-20 | I | `POST /instalacion` válido con 0 usuarios (en producción, con token correcto) | 201, usuario creado con hash Argon2id/bcrypt, `Sesion.usuario` lleno, cookie de sesión |
| CP-015 | RF-20 | I | `POST /instalacion` cuando ya existe un usuario | 409 `ESTADO_INVALIDO`; no se crea nada |
| CP-016 | RF-20 | I | Producción: `POST /instalacion` sin token o con token incorrecto | 403 `PROHIBIDO`; no se crea nada |

### 2.2 Tareas (RF-03, RF-04, RF-05, RF-13, RF-19)

| ID | RF | Tipo | Caso | Resultado esperado |
|---|---|---|---|---|
| CP-020 | RF-03 | U | Recurrencia diaria del 1 al 7 | 7 fechas |
| CP-021 | RF-03 | U | Recurrencia semanal `lu,mi,vi` en 2 semanas | 6 fechas, todas lunes, miércoles o viernes |
| CP-022 | RF-03 | U | Recurrencia con `fecha_fin` antes del rango | 0 fechas |
| CP-023 | RF-03 | I | Generar instancias dos veces el mismo día | Sin duplicados (índice único) |
| CP-024 | RF-03 | I | Editar puntos de una tarea con instancias hecha y pendiente | Solo la pendiente futura cambia de puntos |
| CP-025 | RF-03 | I | Desactivar tarea | Instancias futuras pendientes eliminadas; aprobadas intactas |
| CP-026 | RF-03 | I | Crear tarea de casa con 0 puntos, de cualquier tipo con 101, y actividad con 0 | 400 `VALIDACION` en las dos primeras; 201 en la actividad |
| CP-027 | RF-04 | I | `GET /yo/dia` ordena por hora y deja sin hora al final | Orden correcto |
| CP-028 | RF-04 | I | `GET /yo/dia` de otra hija no es posible | Solo instancias del perfil activo |
| CP-029 | RF-05 | I | Marcar hecha una instancia de hoy | 200, estado `hecha`, `completada_en` lleno, sin movimiento de puntos |
| CP-030 | RF-05 | I | Marcar hecha una instancia de mañana | 409 `ESTADO_INVALIDO` |
| CP-031 | RF-05 | I | Marcar hecha una instancia de otro perfil | 403 `PROHIBIDO` |
| CP-032 | RF-05 | F | Tocar "Listo" muestra celebración y llama `marcarHecha` | Confeti visible; con `reduced-motion` no hay confeti |
| CP-033 | RF-13 | I | Escolar creada hoy con entrega en 3 días | Aparece en "Mi día" hoy, mañana y pasado; desaparece al aprobarse |
| CP-034 | RF-04 | U | Fecha "hoy" a las 23:30 en Ciudad Juárez | Usa la fecha local, no UTC (RN-06) |
| CP-035 | RF-19 | I | Aventurera crea una escolar propia enviando `puntos: 50` y otro `perfil_id` | 201; `creada_por = perfil`, `perfil_id` = el suyo, `puntos = 5`, categoría "Escuela", `una_vez` desde hoy |
| CP-036 | RF-19 | I | Aventurera intenta crear una tarea de tipo `casa` | 403 `PROHIBIDO` |
| CP-037 | RF-19 | I | Exploradora llama `POST /yo/tareas` | 403 `PROHIBIDO` |
| CP-038 | RF-19 | I | Escolar propia marcada hecha y aprobada por el padre | Aparece en `GET /admin/tareas` con `creada_por = perfil` y en la bandeja; al aprobar se acreditan 5 puntos una vez |
| CP-039 | RF-19 | F | Formulario de escolar propia | Solo envía campos de `TareaPropiaEntrada` con `tipo = escolar`; exige materia y entrega no anterior a hoy |

### 2.3 Evidencias y aprobación (RF-06, RF-07, RF-08)

| ID | RF | Tipo | Caso | Resultado esperado |
|---|---|---|---|---|
| CP-040 | RF-06 | I | Subir JPEG de 2 MB con EXIF GPS | Guardado como WebP sin EXIF |
| CP-041 | RF-06 | I | Subir archivo de 4 MB | 400 `VALIDACION` |
| CP-042 | RF-06 | I | Subir PHP renombrado a `.jpg` | 400 `VALIDACION` (MIME real) |
| CP-043 | RF-06 | I | Otra hija pide `GET /evidencias/{id}` | 403 `PROHIBIDO` |
| CP-044 | RF-06 | U | Purga de evidencias vencidas | Archivo y fila eliminados |
| CP-045 | RF-07 | I | Aprobar instancia `hecha` | Estado `aprobada`, revisión creada, movimiento con los puntos |
| CP-046 | RF-07 | I | Aprobar dos veces la misma instancia | Segunda 409; un solo movimiento (RN-04) |
| CP-047 | RF-07 | I | Devolver sin mensaje | 400 `VALIDACION` |
| CP-048 | RF-07 | I | Devolver con mensaje | Estado `devuelta`, mensaje visible en `GET /yo/dia` |
| CP-049 | RF-07 | I | Aprobación en lote de 5 | 5 resultados y 5 movimientos |
| CP-050 | RF-08 | U | Nivel con 0, 99, 100, 299, 300, 3000 XP | Semilla, Semilla, Brote, Brote, Raíz, Selva |
| CP-051 | RF-08 | I | Canje aprobado no baja XP | XP igual, saldo menor (RN-05) |
| CP-052 | RF-08 | I | Enviar `puntos` en el cuerpo de `marcarHecha` | Ignorado; puntos los define la tarea |

### 2.4 Juego (RF-09 a RF-12)

| ID | RF | Tipo | Caso | Resultado esperado |
|---|---|---|---|---|
| CP-060 | RF-09 | I | Solicitar canje con saldo suficiente | 201 `solicitado`; `disponible` reducido |
| CP-061 | RF-09 | I | Solicitar canje sin saldo (contando reservados) | 409 `SALDO_INSUFICIENTE` |
| CP-062 | RF-09 | I | Aprobar canje | Movimiento negativo igual al costo |
| CP-063 | RF-09 | I | Rechazar canje | Sin movimiento; `disponible` restaurado |
| CP-064 | RF-09 | F | Tarjeta "Ya te alcanza" solo cuando `alcanza = true` | Render condicional correcto |
| CP-065 | RF-10 | U | Semana con 10 asignadas y 8 aprobadas, umbral 80 | Cumplida |
| CP-066 | RF-10 | U | Racha con semanas cumplida, cumplida, no, cumplida | Racha = 1 |
| CP-067 | RF-10 | U | Semana sin instancias entre dos cumplidas | Racha = 2 (no rompe) |
| CP-068 | RF-10 | I | Romper racha no crea movimiento negativo | Sin movimientos (RN-01) |
| CP-069 | RF-11 | I | Primera aprobación otorga "Primera misión" y 20 XP | Insignia y movimiento `insignia` |
| CP-070 | RF-11 | I | Evaluar insignias dos veces | Una sola fila en `perfil_insignias` |
| CP-071 | RF-12 | I | Crear segunda meta activa | 409 `ESTADO_INVALIDO` |
| CP-072 | RF-12 | I | Aprobaciones de dos hijas llegan al objetivo | Meta `cumplida`, `ResultadoRevision.meta_cumplida = true` |

### 2.5 Calendario, Pro, panel y configuración (RF-14 a RF-18)

| ID | RF | Tipo | Caso | Resultado esperado |
|---|---|---|---|---|
| CP-080 | RF-14 | I | `GET /yo/semana` | 7 días con sus instancias |
| CP-081 | RF-14 | I | Actividad de 0 puntos marcada hecha | Pasa a `aprobada` sin revisión del padre |
| CP-082 | RF-15 | I | Hija Pro crea tarea propia | `creada_por = perfil`, `puntos = 5` |
| CP-083 | RF-15 | I | Hija Aventurera intenta crear una tarea de tipo `actividad` (solo Pro puede, RF-19) | 403 `PROHIBIDO` |
| CP-084 | RF-15 | I | Hija Pro edita una tarea creada por el padre | 403 `PROHIBIDO` |
| CP-085 | RF-16 | I | `GET /admin/panel` con datos de demo | Porcentajes, pendientes y racha por hija correctos |
| CP-086 | RF-18 | I | Cambiar `racha_umbral` a 60 | Siguiente cierre semanal usa 60 |
| CP-087 | RF-16 | E | Padre entra a modo padre, aprueba 2 tareas y la hija ve sus puntos | XP actualizado en "Mis logros" |

### 2.6 Seguridad (RNF-07, doc 04)

| ID | Control | Tipo | Caso | Resultado esperado |
|---|---|---|---|---|
| CP-SEG-01 | CSRF | I | `POST` sin `X-CSRF-TOKEN` | 403 `CSRF` |
| CP-SEG-02 | Modo padre | I | Hija con perfil activo llama `GET /admin/aprobaciones` | 403 `MODO_PADRE_REQUERIDO` |
| CP-SEG-03 | Throttling | I | 6 logins fallidos en un minuto | 429 `DEMASIADOS_INTENTOS` |
| CP-SEG-04 | Headers | I | Cualquier respuesta | CSP, HSTS (prod), `X-Frame-Options: DENY`, `X-Robots-Tag` presentes |
| CP-SEG-05 | Inyección SQL | I | `titulo` con `'; DROP TABLE tareas; --` | Guardado literal, tabla intacta |
| CP-SEG-06 | Hash | U | Contraseña y PIN guardados | Comienzan con `$argon2id$` o `$2y$` |
| CP-SEG-07 | Cookie | I | Login en entorno producción | `HttpOnly`, `Secure`, `SameSite=Lax` |
| CP-SEG-08 | Archivos | M | `GET /writable/...` y `/.env` en el servidor | 403 o 404 |
| CP-SEG-09 | Terceros | E | Carga de "Mi día" | Ninguna petición a otro dominio |

### 2.7 No funcionales

| ID | RNF | Tipo | Caso | Resultado esperado |
|---|---|---|---|---|
| CP-NF-01 | RNF-01 | E | Lighthouse de "Mi día" con throttling 4G | LCP menor a 1.5 s |
| CP-NF-02 | RNF-03 | F | Botón "Listo" por nivel | Alto mínimo 72, 56 y 44 px |
| CP-NF-03 | RNF-04 | E | axe en las 6 pantallas principales, 3 niveles | 0 violaciones serias o críticas |
| CP-NF-04 | RNF-05 | E | Suite e2e en 768 px y 360 px | PASS en ambos |
| CP-NF-05 | RNF-10 | M | Instalar PWA en Android y en iPhone | Ícono en inicio y abre sin barra del navegador |
| CP-NF-06 | RNF-09 | U | Reporte de cobertura | 85 % o más en Services y Domain |

## 3. Trazabilidad RF → casos → sprint

| RF | Casos | Sprint |
|---|---|---|
| RF-01 | CP-003, CP-004, CP-005, CP-006, CP-009 | S1 |
| RF-02 | CP-001, CP-002, CP-007, CP-008 | S1 |
| RF-03 | CP-020 a CP-026 | S2 |
| RF-04 | CP-027, CP-028, CP-034 | S2 |
| RF-05 | CP-029 a CP-032 | S2 |
| RF-06 | CP-040 a CP-044 | S3 |
| RF-07 | CP-045 a CP-049 | S3 |
| RF-08 | CP-050 a CP-052 | S3 |
| RF-09 | CP-060 a CP-064 | S4 |
| RF-10 | CP-065 a CP-068 | S4 |
| RF-11 | CP-069, CP-070 | S4 |
| RF-12 | CP-071, CP-072 | S4 |
| RF-13 | CP-033 | S2 |
| RF-14 | CP-080, CP-081 | S5 |
| RF-15 | CP-082 a CP-084 | S5 |
| RF-16 | CP-085, CP-087 | S5 |
| RF-17 | CP-010 a CP-012 | S1 |
| RF-18 | CP-086 | S4 |
| RF-20 | CP-013 a CP-016 | S1 |
| RF-19 | CP-035 a CP-039 | S2 |

## 4. Datos de prueba

`DemoSeeder` con fechas relativas a "hoy" para que las pruebas no dependan del calendario. La base de pruebas se recrea en cada corrida (`DatabaseTestTrait` con `$refresh = true`). El reloj se fija con `Time::setTestNow()` en backend y `vi.setSystemTime()` en frontend.

## 5. Guion de sesión con las hijas (manual)

1. Sin explicar nada, pedirle que entre a su perfil y marque una tarea.
2. Observar: ¿encontró su avatar?, ¿entendió el botón?, ¿le gustó la celebración?
3. Preguntar: "¿Qué premio te gustaría?" y "¿Qué cambiarías?".
4. Registrar en el doc 10 del sprint: tiempo hasta completar, dudas y frases textuales.
