# 00 · Auditoría de fuentes

**Proyecto:** Tareas en Familia · **Versión:** 1.0 · **Fecha:** 2026-11-22 · **Responsable:** David García

## 1. Propósito

Registrar de dónde sale cada decisión de esta documentación, qué se verificó y qué queda abierto. Es un proyecto nuevo: no hay código, esquema ni sistema previo que migrar.

## 2. Inventario de fuentes

| ID | Fuente | Tipo | Ubicación | Uso |
|---|---|---|---|---|
| F-01 | Conversación de descubrimiento con David (22-nov-2026) | Decisiones verbales | Chat del proyecto "Desarrollo de Software" | Alcance, usuarias, stack, hosting |
| F-02 | Plan de desarrollo — App de tareas familiar | Documento | Claude Docs (artifact `4xuPLnEzCqYbV6CcpNfTuo`) | Base de los docs 01–07 |
| F-03 | Referencia visual "Dirección D · Formas vivas" | Imagen | `00-fuentes/referencia_formas_vivas.png` | Doc 08 (design system) |
| F-04 | Captura del plan Hostinger Business Web Hosting | Imagen | `00-fuentes/hostinger_plan.png`, `00-fuentes/hostinger_detalles.png` | Restricciones de despliegue |
| F-05 | Metodología v4.0 de Plan Juárez | Estándar de proceso | `Desarrollo de Software/METODOLOGIA_v4.md` | Estructura de fases y documentos |

## 3. Decisiones confirmadas por David

| ID | Decisión | Fuente | Fecha |
|---|---|---|---|
| D-01 | La app es para uso de una sola familia (sin registro público ni multi-familia) | F-01 | 2026-11-22 |
| D-02 | Usuarias: hijas en 1° a 3° de primaria, 4° a 6° de primaria y secundaria | F-01 | 2026-11-22 |
| D-03 | Stack: CodeIgniter 4 + React con Vite + MariaDB | F-01 | 2026-11-22 |
| D-04 | Se descartan Laravel, Filament y Livewire (solicitados inicialmente y reemplazados en el mismo mensaje) | F-01 | 2026-11-22 |
| D-05 | Hosting: Hostinger Business Web Hosting compartido que ya se paga | F-01, F-04 | 2026-11-22 |
| D-06 | Estilo visual: "Formas vivas" (colores suaves, formas redondeadas, DM Sans) | F-01, F-03 | 2026-11-22 |
| D-07 | La app debe ser divertida y amigable para niñas, sin castigos | F-01 | 2026-11-22 |

## 4. Datos verificados del hosting (F-04)

| Recurso | Valor |
|---|---|
| Plan | Business Web Hosting (compartido) |
| Disco | 50 GB |
| RAM | 3072 MB |
| Núcleos de CPU | 2 |
| Inodos | 600 000 |
| Sitios web | 50 |
| Procesos máximos | 120 |
| PHP workers | 60 |
| Ancho de banda | Ilimitado |

Consecuencias: sin root, sin nginx configurable (LiteSpeed administrado), sin Docker, sin Redis, sin procesos permanentes. Hay SSH, Composer, cron y MySQL/MariaDB en planes Business según la documentación pública de Hostinger.

## 5. Huecos abiertos (bloquean el Sprint 0, no la Fase 0)

| ID | Pendiente | Responsable | Cómo se cierra |
|---|---|---|---|
| H-01 | Versión exacta de PHP disponible (se requiere 8.3) | David | hPanel → Avanzado → Configuración de PHP |
| H-02 | Versión exacta de MariaDB del hosting | David | phpMyAdmin → `SELECT VERSION();` |
| H-03 | Dominio o subdominio para la app | David | Elegir en el portafolio de dominios |
| H-04 | Acceso SSH habilitado y llave de despliegue | David | hPanel → Avanzado → Acceso SSH |
| H-05 | Recompensas iniciales (5 a 10) y meta familiar del primer mes | David con sus hijas | Sesión familiar antes del S4 (recompensas) |
| H-06 | Dispositivo principal (tablet familiar o celulares) | David | Define el viewport prioritario del S1 |
| H-07 | Extensión GD de PHP con soporte WebP | David | `php -m` y `php -r "print_r(gd_info());"` por SSH |
| H-08 | `exec()` y `mysqldump` disponibles para el cron | David | `php -r "var_dump(function_exists('exec'));"` y `which mysqldump` por SSH |

## 6. Riesgos de las fuentes

- Los colores de F-03 se tomaron de una imagen; los valores hex del doc 08 son de partida y se ajustan al validar contraste.
- No hay datos reales de uso: los umbrales (racha 80 %, puntos por tarea) son supuestos iniciales configurables.
