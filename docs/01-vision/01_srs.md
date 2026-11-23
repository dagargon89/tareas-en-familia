# 01 · Especificación de requisitos (SRS)

**Proyecto:** Tareas en Familia · **Versión:** 1.0 · **Fecha:** 2026-11-22 · **Fuentes:** F-01, F-02

## 1. Visión

Las hijas abren la app en la tablet o el celular, eligen su avatar, ven su día como una lista de misiones y las marcan con un toque. Cada tarea aprobada da puntos que suben su nivel y se canjean por recompensas que la familia acordó. El padre o la madre crea las tareas, aprueba lo hecho y ve la semana de cada una en un panel.

## 2. Actores

| Actor | Descripción | Acceso |
|---|---|---|
| Padre | Padre o madre. Puede haber hasta 2 cuentas | Correo + contraseña en el dispositivo; PIN de 4 dígitos para modo padre |
| Hija Exploradora | 1° a 3° de primaria | Avatar + PIN de 4 dígitos |
| Hija Aventurera | 4° a 6° de primaria | Avatar + PIN de 4 dígitos |
| Hija Pro | Secundaria | Avatar + PIN de 4 dígitos; crea sus propias tareas |
| Sistema | Cron de tareas programadas | Interno |

El nivel (Exploradora, Aventurera, Pro) es un atributo del perfil (`perfiles.nivel_ux`) y lo cambia el padre.

## 3. Glosario

| Término | Definición |
|---|---|
| Tarea | Plantilla: qué hay que hacer, quién, cuántos puntos y cada cuándo |
| Instancia | La tarea en un día concreto; es lo que la hija marca |
| XP | Puntos ganados acumulados de por vida; definen el nivel y nunca bajan |
| Saldo | Puntos disponibles para canjear: XP menos canjes aprobados |
| Racha | Semanas ISO consecutivas en que la hija cumplió el umbral de tareas |
| Meta familiar | Objetivo de puntos que suman todas las hijas juntas |
| Modo padre | Estado de la sesión con permisos de administración, protegido por PIN |

## 4. Requisitos funcionales

Formato del criterio de aceptación: *Dado / Cuando / Entonces*. Cada RF tiene al menos un caso en el doc 06.

### Acceso

**RF-01 · Selector de perfil con PIN**
Prioridad: Alta · Sprint: S1
- Dado un dispositivo con sesión familiar abierta, cuando se abre la app, entonces se muestran los avatares de los perfiles activos.
- Cuando la hija toca su avatar y escribe su PIN correcto, entonces entra a "Mi día".
- Cuando el PIN es incorrecto 5 veces seguidas, entonces el perfil queda bloqueado 15 minutos y se muestra un mensaje amable.

**RF-02 · Acceso del padre y modo padre**
Prioridad: Alta · Sprint: S1
- Cuando el padre inicia sesión con correo y contraseña, entonces el dispositivo queda con sesión familiar por 30 días.
- Cuando desde cualquier pantalla se toca "Modo padre" y se escribe el PIN del padre que abrió la sesión del dispositivo (`usuario_id` de la sesión), entonces se habilitan las pantallas de administración.
- Cuando pasan 10 minutos sin actividad en modo padre, entonces el modo padre se cierra solo.

**RF-20 · Instalación inicial**
Prioridad: Alta · Sprint: S1
- Dado que la base no tiene ningún usuario, cuando se abre la app, entonces se muestra la pantalla "Configura la app" en lugar del login.
- El padre escribe nombre, correo, contraseña (mínimo 10 caracteres) y su PIN de modo padre (sin PIN trivial); al terminar queda creado y con la sesión del dispositivo abierta.
- En producción se pide además el token de instalación definido en el `.env` del servidor (`instalacion.token`); en desarrollo no.
- En cuanto existe un usuario, la instalación queda cerrada para siempre: la pantalla ya no se ofrece y el endpoint responde 409. Los usuarios siguientes (el segundo padre) se crean con `php spark usuarios:crear`.

### Tareas

**RF-03 · Crear y editar tareas**
Prioridad: Alta · Sprint: S2
- El padre crea una tarea con: título (máx. 80 caracteres), ícono del catálogo, categoría, perfil asignado, puntos (1 a 100; 0 a 100 solo si el tipo es `actividad`), tipo (casa, escolar, actividad), hora opcional, si requiere foto, y recurrencia.
- Recurrencias soportadas: una vez (fecha), diaria, días de la semana (uno o varios), con fecha de inicio y fin opcional.
- Editar una tarea afecta solo instancias futuras pendientes; las hechas o aprobadas no cambian.
- Desactivar una tarea elimina sus instancias futuras pendientes y conserva el historial.

**RF-04 · Vista "Mi día"**
Prioridad: Alta · Sprint: S2
- La hija ve las instancias de hoy ordenadas por hora (sin hora al final), con ícono, título, puntos y estado.
- Puede moverse a días anteriores (hasta 7) y siguientes (hasta 7).
- Las tareas escolares muestran la materia y la fecha de entrega.

**RF-05 · Marcar como hecha**
Prioridad: Alta · Sprint: S2
- Cuando la hija toca "Listo" en una instancia pendiente o devuelta de hoy o de días pasados, entonces cambia a `hecha`, se reproduce la celebración (animación y sonido) y queda a la espera de aprobación.
- No se puede marcar una instancia de un día futuro.
- Si la tarea requiere foto, el botón abre la cámara o galería antes de marcar.

**RF-06 · Foto de evidencia**
Prioridad: Media · Sprint: S3
- Formatos JPEG, PNG o WebP de hasta 3 MB. Se re-codifica a WebP o JPEG sin metadatos.
- Solo la dueña del perfil y el padre pueden ver la foto.
- La foto se borra 30 días después de aprobada la instancia.

**RF-07 · Aprobación del padre**
Prioridad: Alta · Sprint: S3
- El padre ve una bandeja con las instancias `hecha` de todas las hijas, la más antigua primero.
- Aprobar cambia a `aprobada` y acredita los puntos en una sola transacción.
- Devolver cambia a `devuelta` con un mensaje amable obligatorio (máx. 140 caracteres) que la hija ve en su tarjeta.
- Aprobación en lote: seleccionar varias y aprobarlas juntas.

### Juego

**RF-08 · Puntos, XP y niveles**
Prioridad: Alta · Sprint: S3
- Al aprobar una instancia se inserta un movimiento positivo con los puntos de la tarea.
- XP = suma de movimientos positivos de origen `tarea` e `insignia`. Saldo = suma de todos los movimientos.
- Niveles por XP: Semilla (0), Brote (100), Raíz (300), Árbol (700), Bosque (1500), Selva (3000).
- La hija ve su nivel, la barra hacia el siguiente y cuánto le falta.

**RF-09 · Recompensas y canjes**
Prioridad: Alta · Sprint: S4
- El padre administra un catálogo: título, ícono, costo en puntos, activa.
- La hija ve el catálogo con lo que "ya le alcanza" destacado y puede pedir un canje si su saldo alcanza.
- El canje queda `solicitado` y reserva el saldo; al aprobarlo se inserta un movimiento negativo; al rechazarlo se libera.
- El padre marca el canje como `entregado` cuando cumple la recompensa.

**RF-10 · Rachas semanales**
Prioridad: Media · Sprint: S4
- Una semana ISO (lunes a domingo) cuenta como cumplida si la hija aprobó al menos el umbral de sus instancias (configurable, por defecto 80 %).
- La racha es el número de semanas cumplidas consecutivas hasta la última semana cerrada.
- Una semana no cumplida pone la racha en 0; nunca resta puntos.
- Semanas sin instancias asignadas no rompen ni suman racha.

**RF-11 · Insignias**
Prioridad: Media · Sprint: S4
- Catálogo inicial: Primera misión, 10 misiones, 50 misiones, 100 misiones, Racha de 2 semanas, Racha de 4 semanas, 10 tareas escolares, 10 tareas de casa.
- Una insignia se otorga una sola vez por perfil y da 20 XP.
- Las insignias bloqueadas se muestran con candado punteado y el requisito.

**RF-12 · Meta familiar**
Prioridad: Media · Sprint: S4
- El padre define una meta: título, premio, puntos objetivo y fecha límite opcional. Solo una meta activa a la vez.
- Todos los puntos de tareas aprobadas desde el inicio de la meta suman a la barra común.
- Al llegar al objetivo la meta pasa a `cumplida` y todas ven la celebración.

### Escuela y actividades

**RF-13 · Tareas escolares**
Prioridad: Alta · Sprint: S2
- Una tarea de tipo `escolar` tiene materia (texto libre, máx. 40) y fecha de entrega.
- Aparece en "Mi día" desde que se crea hasta su fecha de entrega o hasta que se aprueba.

**RF-14 · Calendario de actividades**
Prioridad: Media · Sprint: S5
- Vista semanal con las instancias por día de cada hija (o de todas, en modo padre).
- Las actividades extracurriculares (tipo `actividad`) se muestran con su hora y no requieren aprobación si tienen 0 puntos.

**RF-15 · Autonomía del nivel Pro**
Prioridad: Media · Sprint: S5
- La hija Pro puede crear, editar y desactivar sus propias tareas de cualquier tipo (`creada_por = perfil`). Las escolares propias se adelantan al S2 con RF-19.
- Sus tareas propias valen los puntos fijos de `puntos_tarea_propia` (5 por defecto) y requieren aprobación del padre como las demás.
- No puede ver ni editar tareas de otros perfiles ni las creadas por el padre (solo completarlas).

**RF-19 · Tareas escolares propias (Aventureras y Pro)**
Prioridad: Alta · Sprint: S2
- Las hijas de nivel Aventureras y Pro pueden crear sus propias tareas escolares (título, materia, fecha de entrega, ícono y hora opcional), para que el padre sepa qué les dejaron en la escuela.
- La tarea queda con `creada_por = perfil`, asignada a quien la creó, categoría "Escuela", recurrencia `una_vez` desde el día en que se crea, y vale los puntos fijos de `puntos_tarea_propia` (5 por defecto).
- Los puntos solo se acreditan cuando el padre aprueba que la hizo (RN-03), igual que cualquier otra tarea; crearla no da puntos.
- Puede editar o desactivar sus propias escolares mientras no estén aprobadas. No puede ver ni editar tareas de otros perfiles ni las del padre.
- Las Aventureras solo pueden crear tareas de tipo `escolar`. Las Exploradoras no crean tareas.
- El padre ve las escolares creadas por las hijas en su lista de tareas, marcadas como "creada por ella" y con un filtro para verlas juntas, y puede editarlas o desactivarlas. También aparecen en las próximas entregas del panel (RF-16).

**RF-16 · Panel del padre**
Prioridad: Alta · Sprint: S5
- Muestra por hija: porcentaje de la semana, pendientes por aprobar, racha, saldo y próximas entregas escolares.
- Muestra la meta familiar y los canjes pendientes.

### Administración

**RF-17 · Gestión de perfiles**
Prioridad: Alta · Sprint: S1
- El padre crea, edita y desactiva perfiles: apodo, avatar, color, nivel UX y PIN.
- Puede restablecer el PIN de un perfil y desbloquearlo.

**RF-18 · Configuración familiar**
Prioridad: Baja · Sprint: S4
- El padre ajusta: umbral de racha, sonidos activados por defecto y su propio PIN (el del usuario de la sesión; el PIN del otro padre no se puede cambiar desde aquí).

## 5. Reglas de negocio

| ID | Regla |
|---|---|
| RN-01 | No existen puntos negativos por tareas no hechas ni marcas rojas |
| RN-02 | No se muestran rankings ni comparaciones entre hermanas |
| RN-03 | Los puntos solo se acreditan al aprobar, nunca al marcar |
| RN-04 | Una instancia se aprueba una sola vez; repetir la aprobación no acredita doble |
| RN-05 | El XP nunca baja; los canjes solo reducen el saldo |
| RN-06 | La zona horaria de la app es America/Ciudad_Juarez para definir "hoy" |
| RN-07 | Las instancias se generan para hoy y los 7 días siguientes |

## 6. Requisitos no funcionales

| ID | Categoría | Requisito | Verificación |
|---|---|---|---|
| RNF-01 | Rendimiento | "Mi día" carga en menos de 1.5 s en 4G con caché vacía | Lighthouse en Playwright |
| RNF-02 | Rendimiento | Respuesta de API p95 menor a 300 ms en el hosting | Log de tiempos en `writable/logs` |
| RNF-03 | Usabilidad | Objetivos táctiles de 72 px (Exploradoras), 56 px (Aventureras), 44 px (Pro) | Prueba de componentes |
| RNF-04 | Accesibilidad | WCAG 2.2 AA en contraste y etiquetas; respeta `prefers-reduced-motion` | axe-core |
| RNF-05 | Compatibilidad | Chrome y Safari de los últimos 2 años; tablet 768 px y celular 360 px | Playwright, 2 viewports |
| RNF-06 | Privacidad | Sin analytics ni recursos de terceros en tiempo de ejecución | Revisión de CSP y red |
| RNF-07 | Seguridad | Controles del doc 04 aplicados al 100 % | Casos CP-SEG del doc 06 |
| RNF-08 | Disponibilidad | Respaldo diario de la base; restauración probada | Doc 11 |
| RNF-09 | Mantenibilidad | Cobertura de pruebas de Services del backend de 85 % o más | PHPUnit con coverage |
| RNF-10 | Instalación | Instalable como PWA en Android y iOS | Prueba manual documentada |

## 7. Fuera de alcance de la versión 1.0

Notificaciones push, mascota que evoluciona, propuestas de tarea de Aventureras, gráficas mensuales, exportación de datos, multi-familia, app nativa.
