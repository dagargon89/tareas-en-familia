# 08 · Design system — "Formas vivas"

**Proyecto:** Tareas en Familia · **Versión:** 1.0 · **Fecha:** 2026-11-22 · **Fuente:** F-03 (`00-fuentes/referencia_formas_vivas.png`)

## 1. Principios

1. **Celebrar, nunca castigar.** No hay rojo de error en la vista de las hijas; un problema se dice con lavanda o ámbar y palabras amables.
2. **Un toque.** La acción principal de cada pantalla cabe en un botón grande y visible.
3. **Formas que respiran.** Contenedores con radios generosos y formas decorativas suaves (flor, píldora, gota) que se mueven poco.
4. **Una sola familia tipográfica.** DM Sans en todo.
5. **El nivel cambia la densidad, no la marca.** Las tres experiencias comparten paleta y componentes.

## 2. Tokens

Se publican como variables CSS en `src/web/src/styles/tokens.css` y se exponen a Tailwind 4 con `@theme`.

### 2.1 Color

| Token | Hex de partida | Uso |
|---|---|---|
| `--color-indigo-600` | `#3F4FB8` | Acción principal, avatar, barra de XP, enlaces |
| `--color-indigo-700` | `#2F3C94` | Hover y texto sobre lavanda |
| `--color-lavanda-100` | `#E4E6FB` | Botón secundario, chips de puntos, tarjeta "Ya te alcanza" |
| `--color-ambar-200` | `#FFE08A` | Tarjeta de horas o puntos, insignia de tiempo |
| `--color-menta-200` | `#C9EFD5` | Logros, categoría escuela, créditos |
| `--color-rosa-200` | `#FFD7DE` | Racha, celebraciones |
| `--color-tinta-900` | `#16161A` | Texto principal |
| `--color-tinta-600` | `#5A5A66` | Texto secundario |
| `--color-fondo` | `#FBFAFF` | Fondo general |
| `--color-superficie` | `#F1F1F4` | Panel lateral y tarjetas neutras |
| `--color-blanco` | `#FFFFFF` | Tarjetas internas |

Validación obligatoria en Sprint 0: todo texto sobre su fondo debe cumplir contraste 4.5:1 (texto normal) o 3:1 (texto de 24 px o más). Si un par falla, se oscurece el texto, nunca se cambia el tono del fondo.

Modo oscuro (solo nivel Pro y opcional en Aventureras): fondo `#15151C`, superficie `#20202A`, texto `#F2F2F7`; los acentos se usan al 20 % de opacidad como fondo de tarjeta.

### 2.2 Tipografía

DM Sans servida desde el propio dominio (`/app/fonts/`), pesos 400, 500 y 700, `font-display: swap`.

| Rol | Exploradoras | Aventureras | Pro |
|---|---|---|---|
| Display (saludo, nivel) | 40/44 · 700 | 34/40 · 700 | 28/34 · 700 |
| Título de tarjeta | 24/30 · 700 | 20/26 · 700 | 17/24 · 600 |
| Cuerpo | 22/30 · 500 | 18/26 · 400 | 16/24 · 400 |
| Etiqueta y chip | 18/24 · 500 | 15/20 · 500 | 13/18 · 500 |

### 2.3 Espaciado, radios y sombras

| Token | Valor |
|---|---|
| Escala de espaciado | 4, 8, 12, 16, 24, 32, 48 px |
| `--radius-tarjeta` | 24 px |
| `--radius-tarjeta-interna` | 20 px |
| `--radius-boton` | 999 px (píldora) |
| `--radius-icono` | 16 px |
| Sombra | Ninguna; la separación se hace con color de superficie |

### 2.4 Movimiento

| Token | Valor | Uso |
|---|---|---|
| `--dur-rapida` | 150 ms | Hover, presión |
| `--dur-media` | 300 ms | Entrada de tarjetas |
| `--dur-celebracion` | 1200 ms | Confeti y salto de la tarjeta al completar |
| Curva | `cubic-bezier(.34,1.56,.64,1)` | Rebote suave |

Con `prefers-reduced-motion: reduce` se desactivan confeti, rebotes y formas flotantes; queda un cambio de color de 150 ms.

## 3. Niveles de experiencia

| Aspecto | Exploradoras (1° a 3°) | Aventureras (4° a 6°) | Pro (secundaria) |
|---|---|---|---|
| Vocabulario | "Misiones", "estrellas" | "Misiones", "XP", "insignias" | "Tareas", "puntos", "racha" |
| Objetivo táctil mínimo | 72 px | 56 px | 44 px |
| Íconos | 48 px, protagonistas | 32 px | 24 px |
| Texto en tarjetas | Solo título corto | Título + puntos | Título, puntos, materia y hora |
| Celebración | Confeti + sonido + salto | Barra de XP que avanza + sonido | Check animado + contador |
| Formas decorativas | Muchas, flotando | Algunas, estáticas | Ninguna |
| Modo oscuro | No | Opcional | Opcional, por defecto según el sistema |
| Navegación | 3 botones grandes: Mi día, Premios, Mis insignias | 4 pestañas | 5 pestañas + crear tarea |

## 4. Componentes

| Componente | Descripción | Estados |
|---|---|---|
| `AvatarSelector` | Rejilla de avatares grandes con el apodo debajo | normal, bloqueado (candado y tiempo restante) |
| `PinPad` | Teclado de 4 dígitos con puntos que se llenan | normal, error (sacudida suave, sin rojo), bloqueado |
| `TaskCard` | Ícono en forma de color de categoría, título, chips de puntos, botón Listo | pendiente, hecha (esperando), aprobada (check menta), devuelta (mensaje lavanda) |
| `DoneButton` | Píldora índigo de ancho completo en Exploradoras, compacta en Pro | normal, presionado, cargando |
| `PointsChip` | Píldora pequeña: "+4 XP" en ámbar, lavanda o rosa | — |
| `XpWaveBar` | Barra de progreso con trazo ondulado índigo y texto "Te faltan N para Árbol" | — |
| `StatTile` | Tarjeta de color (ámbar o menta) con etiqueta y número grande | — |
| `StreakBadge` | Píldora rosa "Racha: N semanas" | activa, en cero ("¡Empieza tu racha!") |
| `BadgeGrid` | Rejilla de insignias: forma de color + ícono + nombre | obtenida, bloqueada (borde punteado y candado) |
| `RewardCard` | Tarjeta lavanda "Ya te alcanza" con botón Canjear | alcanza, no alcanza (muestra cuánto falta), solicitada |
| `FamilyGoalBar` | Barra común con los avatares sobre el progreso | en curso, cumplida |
| `ParentApprovalItem` | Fila con avatar de la hija, tarea, foto miniatura, Aprobar y Devolver | — |
| `WeekCalendar` | Siete columnas con chips de tareas por día | — |
| `BlobShapes` | Formas decorativas SVG (flor ámbar, píldora rosa, gota menta) | animada, estática |

## 5. Íconos y sonidos

- Íconos: Lucide (MIT), incluidos en el bundle; catálogo de 40 íconos para tareas en `src/web/src/lib/iconos.ts`.
- Avatares: 16 ilustraciones propias en SVG (animales y formas) en `src/web/public/avatares/`.
- Sonidos: 4 archivos cortos en OGG y MP3 (completar, nivel nuevo, insignia, canje) de menos de 30 KB cada uno, con licencia libre registrada en `src/web/public/sonidos/LICENCIAS.md`.

## 6. Voz y textos

| Situación | Exploradoras | Pro |
|---|---|---|
| Tarea completada | "¡Lo lograste! ⭐ Ahora papá la revisa" | "Listo. Pendiente de aprobación" |
| Devuelta | "Casi. Papá dice: …" | "Devuelta: …" |
| PIN incorrecto | "Uy, ese no es. Intenta otra vez" | "PIN incorrecto" |
| Bloqueo | "Descansa un ratito y vuelve en 15 minutos" | "Bloqueado 15 min" |
| Día vacío | "¡Hoy no hay misiones! Día libre 🎈" | "Sin tareas hoy" |
