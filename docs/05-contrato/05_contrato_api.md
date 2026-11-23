# 05 · Contrato de API

**Proyecto:** Tareas en Familia · **Versión:** 1.0 (borrador; se congela al cerrar Fase 1) · **Fecha:** 2026-11-22

## 1. Convenciones

| Aspecto | Regla |
|---|---|
| Base | `/api/v1`, mismo dominio que la SPA |
| Formato | JSON UTF-8. Nombres de campo en `snake_case` |
| Fechas | `YYYY-MM-DD`; fecha-hora ISO 8601 con offset (`2026-11-22T18:30:00-07:00`); horas `HH:MM` |
| Autenticación | Cookie de sesión `tf_sesion` (ADR-003) |
| CSRF | Header `X-CSRF-TOKEN` en `POST`, `PUT`, `PATCH`, `DELETE`. El token se obtiene con `GET /csrf` y se renueva con cada respuesta en el header `X-CSRF-TOKEN` |
| Éxito | `200` con el recurso, `201` al crear, `204` sin cuerpo |
| Error | `{ "error": { "codigo": "PIN_INCORRECTO", "mensaje": "…", "campos": { "titulo": "…" } } }` |
| Paginación | `?pagina=1&por_pagina=20` → `{ "datos": [...], "meta": { "pagina", "por_pagina", "total" } }` |

### Códigos de error

| HTTP | `codigo` | Cuándo |
|---|---|---|
| 400 | `VALIDACION` | Campos inválidos (`campos` trae el detalle) |
| 401 | `SIN_SESION` | No hay sesión de dispositivo |
| 401 | `CREDENCIALES` | Correo o contraseña incorrectos |
| 401 | `PIN_INCORRECTO` | PIN incorrecto; incluye `intentos_restantes` |
| 403 | `SIN_PERFIL` | Ruta `/yo/*` sin perfil activo |
| 403 | `MODO_PADRE_REQUERIDO` | Ruta de administración sin modo padre vigente |
| 403 | `PROHIBIDO` | El recurso pertenece a otro perfil |
| 403 | `CSRF` | Token ausente o inválido |
| 404 | `NO_ENCONTRADO` | Recurso inexistente |
| 409 | `ESTADO_INVALIDO` | Transición no permitida (aprobar algo no `hecha`, marcar un día futuro) |
| 409 | `SALDO_INSUFICIENTE` | Canje sin puntos disponibles |
| 423 | `BLOQUEADO` | Perfil o modo padre bloqueado; incluye `bloqueado_hasta` |
| 429 | `DEMASIADOS_INTENTOS` | Throttling |

## 2. Endpoints

Columna **Filtro**: `—` público · `S` sesión de dispositivo · `P` perfil activo · `MP` modo padre.

### 2.1 Sistema y acceso

| ID | Método y ruta | Filtro | Cuerpo | Respuesta | RF |
|---|---|---|---|---|---|
| E-01 | `GET /salud` | — | — | `{ estado: "ok", version }` | — |
| E-02 | `GET /csrf` | — | — | `{ token }` | — |
| E-03 | `POST /auth/login` | — | `{ email, password }` | `Sesion` | RF-02 |
| E-04 | `POST /auth/logout` | S | — | `204` | RF-02 |
| E-05 | `GET /sesion` | S | — | `Sesion` | RF-01, RF-02 |
| E-06 | `GET /perfiles` | S | — | `PerfilPublico[]` | RF-01 |
| E-07 | `POST /perfiles/{id}/entrar` | S | `{ pin }` | `Sesion` | RF-01 |
| E-08 | `POST /perfiles/salir` | S | — | `Sesion` | RF-01 |
| E-09 | `POST /modo-padre` | S | `{ pin }` | `Sesion` | RF-02 |
| E-10 | `DELETE /modo-padre` | S | — | `Sesion` | RF-02 |

### 2.2 Hija (perfil activo)

| ID | Método y ruta | Filtro | Cuerpo | Respuesta | RF |
|---|---|---|---|---|---|
| E-11 | `GET /yo/dia?fecha=` | P | — | `Dia` | RF-04, RF-13 |
| E-12 | `POST /yo/instancias/{id}/hecha` | P | `multipart`: `foto?` | `Instancia` | RF-05, RF-06 |
| E-13 | `GET /yo/progreso` | P | — | `Progreso` | RF-08, RF-10, RF-11, RF-12 |
| E-14 | `GET /yo/recompensas` | P | — | `RecompensaParaPerfil[]` | RF-09 |
| E-15 | `POST /yo/canjes` | P | `{ recompensa_id }` | `Canje` (201) | RF-09 |
| E-16 | `GET /yo/canjes` | P | — | `Canje[]` | RF-09 |
| E-17 | `GET /yo/semana?desde=` | P | — | `Semana` | RF-14 |
| E-18 | `GET /yo/tareas` | P (Pro) | — | `Tarea[]` (propias) | RF-15 |
| E-19 | `POST /yo/tareas` | P (Pro) | `TareaPropiaEntrada` | `Tarea` (201) | RF-15 |
| E-20 | `PUT /yo/tareas/{id}` | P (Pro) | `TareaPropiaEntrada` | `Tarea` | RF-15 |
| E-21 | `DELETE /yo/tareas/{id}` | P (Pro) | — | `204` (desactiva) | RF-15 |
| E-22 | `PATCH /yo/preferencias` | P | `{ sonidos?, tema? }` | `PerfilPublico` | RF-18 |

### 2.3 Padre (modo padre)

| ID | Método y ruta | Filtro | Cuerpo | Respuesta | RF |
|---|---|---|---|---|---|
| E-23 | `GET /admin/perfiles` | MP | — | `PerfilAdmin[]` | RF-17 |
| E-24 | `POST /admin/perfiles` | MP | `PerfilEntrada` | `PerfilAdmin` (201) | RF-17 |
| E-25 | `PUT /admin/perfiles/{id}` | MP | `PerfilEntrada` (pin opcional) | `PerfilAdmin` | RF-17 |
| E-26 | `POST /admin/perfiles/{id}/desbloquear` | MP | — | `PerfilAdmin` | RF-17 |
| E-27 | `DELETE /admin/perfiles/{id}?definitivo=` | MP | — | `204` | RF-17 |
| E-28 | `GET /admin/tareas?perfil_id=&activa=` | MP | — | `Tarea[]` | RF-03 |
| E-29 | `POST /admin/tareas` | MP | `TareaEntrada` | `Tarea` (201) | RF-03, RF-13 |
| E-30 | `PUT /admin/tareas/{id}` | MP | `TareaEntrada` | `Tarea` | RF-03 |
| E-31 | `DELETE /admin/tareas/{id}` | MP | — | `204` (desactiva) | RF-03 |
| E-32 | `GET /admin/categorias` | MP | — | `Categoria[]` | RF-03 |
| E-33 | `GET /admin/aprobaciones` | MP | — | `InstanciaRevision[]` | RF-07 |
| E-34 | `POST /admin/instancias/{id}/revision` | MP | `{ decision, mensaje? }` | `ResultadoRevision` | RF-07, RF-08 |
| E-35 | `POST /admin/instancias/revision-lote` | MP | `{ ids: number[] }` | `ResultadoRevision[]` | RF-07 |
| E-36 | `GET /evidencias/{id}` | S (dueña o MP) | — | Imagen WebP | RF-06 |
| E-37 | `GET /admin/recompensas` | MP | — | `Recompensa[]` | RF-09 |
| E-38 | `POST /admin/recompensas` | MP | `RecompensaEntrada` | `Recompensa` (201) | RF-09 |
| E-39 | `PUT /admin/recompensas/{id}` | MP | `RecompensaEntrada` | `Recompensa` | RF-09 |
| E-40 | `GET /admin/canjes?estado=` | MP | — | `Canje[]` | RF-09 |
| E-41 | `POST /admin/canjes/{id}/revision` | MP | `{ decision: "aprobado" \| "rechazado" \| "entregado" }` | `Canje` | RF-09 |
| E-42 | `GET /admin/metas` | MP | — | `Meta[]` | RF-12 |
| E-43 | `POST /admin/metas` | MP | `MetaEntrada` | `Meta` (201) | RF-12 |
| E-44 | `PUT /admin/metas/{id}` | MP | `MetaEntrada & { estado? }` | `Meta` | RF-12 |
| E-45 | `GET /admin/panel?semana=` | MP | — | `Panel` | RF-16 |
| E-46 | `GET /admin/semana?desde=&perfil_id=` | MP | — | `Semana` | RF-14 |
| E-47 | `GET /admin/configuracion` | MP | — | `Configuracion` | RF-18 |
| E-48 | `PUT /admin/configuracion` | MP | `ConfiguracionEntrada` | `Configuracion` | RF-18 |

### 2.4 Reglas de transición

| Recurso | De | A | Quién | Endpoint |
|---|---|---|---|---|
| Instancia | `pendiente`, `devuelta` | `hecha` | Dueña, fecha ≤ hoy | E-12 |
| Instancia | `hecha` | `aprobada` | Padre | E-34, E-35 |
| Instancia | `hecha` | `devuelta` | Padre, mensaje obligatorio | E-34 |
| Canje | `solicitado` | `aprobado` / `rechazado` | Padre | E-41 |
| Canje | `aprobado` | `entregado` | Padre | E-41 |
| Meta | `activa` | `cumplida` | Sistema al llegar al objetivo | Interno |
| Meta | `activa` | `cancelada` | Padre o vencimiento | E-44, cron |

Las instancias de tipo `actividad` con 0 puntos pasan de `hecha` a `aprobada` automáticamente (RF-14).

## 3. Tipos y `ApiClient` (contrato único)

Archivo: `src/web/src/api/ApiClient.ts`. Es el único punto de acceso a la API desde la SPA. Al cerrar la Fase 1, esta sección se congela; cualquier cambio posterior sigue el control de cambios de la Metodología v4 §11.

```ts
// ===== Tipos base =====
export type NivelUx = 'exploradoras' | 'aventureras' | 'pro';
export type ColorToken = 'indigo' | 'ambar' | 'menta' | 'rosa' | 'lavanda';
export type TipoTarea = 'casa' | 'escolar' | 'actividad';
export type EstadoInstancia = 'pendiente' | 'hecha' | 'aprobada' | 'devuelta';
export type EstadoCanje = 'solicitado' | 'aprobado' | 'entregado' | 'rechazado';
export type EstadoMeta = 'activa' | 'cumplida' | 'cancelada';
export type DiaSemana = 'lu' | 'ma' | 'mi' | 'ju' | 'vi' | 'sa' | 'do';
export type Frecuencia = 'una_vez' | 'diaria' | 'semanal';

export interface ApiError { codigo: string; mensaje: string; campos?: Record<string, string>;
  intentos_restantes?: number; bloqueado_hasta?: string; }

// ===== Acceso =====
export interface Sesion {
  usuario: { id: number; nombre: string } | null;
  perfil_activo: PerfilPublico | null;
  modo_padre_hasta: string | null;
}
export interface PerfilPublico {
  id: number; apodo: string; avatar: string; color: ColorToken; nivel_ux: NivelUx;
  sonidos: boolean; tema: 'claro' | 'oscuro' | 'sistema'; bloqueado_hasta: string | null;
}
export interface PerfilAdmin extends PerfilPublico { activo: boolean; orden: number; pin_intentos: number; }
export interface PerfilEntrada { apodo: string; avatar: string; color: ColorToken; nivel_ux: NivelUx;
  pin?: string; orden?: number; activo?: boolean; }

// ===== Tareas =====
export interface Recurrencia { frecuencia: Frecuencia; dias_semana: DiaSemana[] | null;
  hora: string | null; fecha_inicio: string; fecha_fin: string | null; }
export interface Tarea {
  id: number; perfil_id: number; categoria_id: number; titulo: string; icono: string; puntos: number;
  tipo: TipoTarea; materia: string | null; fecha_entrega: string | null; requiere_foto: boolean;
  creada_por: 'padre' | 'perfil'; activa: boolean; recurrencia: Recurrencia;
}
export interface TareaEntrada {
  perfil_id: number; categoria_id: number; titulo: string; icono: string; puntos: number; tipo: TipoTarea;
  materia?: string | null; fecha_entrega?: string | null; requiere_foto?: boolean; recurrencia: Recurrencia;
}
export type TareaPropiaEntrada = Omit<TareaEntrada, 'perfil_id' | 'puntos'>;
export interface Categoria { id: number; nombre: string; icono: string; color: ColorToken; }

export interface Instancia {
  id: number; tarea_id: number; perfil_id: number; fecha: string; hora: string | null;
  fecha_entrega: string | null; titulo: string; icono: string; color: ColorToken; tipo: TipoTarea;
  materia: string | null; puntos: number; estado: EstadoInstancia; requiere_foto: boolean;
  tiene_evidencia: boolean; evidencia_id: number | null; mensaje_devolucion: string | null;
  completada_en: string | null;
}
export interface Dia { fecha: string; es_hoy: boolean; instancias: Instancia[];
  resumen: { total: number; hechas: number; aprobadas: number }; }
export interface Semana { desde: string; hasta: string; dias: Dia[]; }

// ===== Juego =====
export interface Nivel { nombre: string; xp_desde: number; xp_hasta: number | null; }
export interface Insignia { codigo: string; nombre: string; descripcion: string; icono: string;
  color: ColorToken; obtenida_en: string | null; }
export interface Meta { id: number; titulo: string; premio: string; objetivo_puntos: number;
  progreso_puntos: number; fecha_inicio: string; fecha_limite: string | null; estado: EstadoMeta; }
export interface MetaEntrada { titulo: string; premio: string; objetivo_puntos: number;
  fecha_inicio: string; fecha_limite?: string | null; }
export interface Progreso {
  xp: number; saldo: number; disponible: number; nivel: Nivel; siguiente_nivel: Nivel | null;
  racha_semanas: number; semana_actual: { asignadas: number; aprobadas: number; porcentaje: number };
  insignias: Insignia[]; meta_familiar: Meta | null;
}
export interface Recompensa { id: number; titulo: string; icono: string; costo: number; activa: boolean; }
export type RecompensaEntrada = Omit<Recompensa, 'id'>;
export interface RecompensaParaPerfil extends Recompensa { alcanza: boolean; faltan: number; }
export interface Canje { id: number; perfil_id: number; recompensa: Recompensa; costo: number;
  estado: EstadoCanje; created_at: string; }

// ===== Padre =====
export interface InstanciaRevision extends Instancia { perfil: PerfilPublico; }
export interface ResultadoRevision { instancia: Instancia; puntos_acreditados: number;
  insignias_nuevas: Insignia[]; meta_cumplida: boolean; }
export interface PanelHija { perfil: PerfilPublico; porcentaje_semana: number; por_aprobar: number;
  racha_semanas: number; disponible: number; proximas_entregas: Instancia[]; }
export interface Panel { semana_iso: string; hijas: PanelHija[]; meta_familiar: Meta | null;
  canjes_pendientes: Canje[]; }
export interface Configuracion { racha_umbral: number; sonidos_default: boolean; dias_generacion: number;
  evidencia_dias_retencion: number; puntos_tarea_propia_pro: number; }
export interface ConfiguracionEntrada { racha_umbral?: number; sonidos_default?: boolean; pin_padre?: string; } // pin_padre cambia el PIN del usuario de la sesión

// ===== Cliente único =====
export interface ApiClient {
  // Sistema y acceso
  salud(): Promise<{ estado: 'ok'; version: string }>;                         // E-01
  login(email: string, password: string): Promise<Sesion>;                     // E-03
  logout(): Promise<void>;                                                     // E-04
  sesion(): Promise<Sesion>;                                                   // E-05
  perfiles(): Promise<PerfilPublico[]>;                                        // E-06
  entrarPerfil(id: number, pin: string): Promise<Sesion>;                      // E-07
  salirPerfil(): Promise<Sesion>;                                              // E-08
  entrarModoPadre(pin: string): Promise<Sesion>;                               // E-09
  salirModoPadre(): Promise<Sesion>;                                           // E-10
  // Hija
  dia(fecha?: string): Promise<Dia>;                                           // E-11
  marcarHecha(instanciaId: number, foto?: File): Promise<Instancia>;           // E-12
  progreso(): Promise<Progreso>;                                               // E-13
  recompensasPerfil(): Promise<RecompensaParaPerfil[]>;                        // E-14
  solicitarCanje(recompensaId: number): Promise<Canje>;                        // E-15
  misCanjes(): Promise<Canje[]>;                                               // E-16
  semana(desde?: string): Promise<Semana>;                                     // E-17
  misTareas(): Promise<Tarea[]>;                                               // E-18
  crearTareaPropia(t: TareaPropiaEntrada): Promise<Tarea>;                     // E-19
  editarTareaPropia(id: number, t: TareaPropiaEntrada): Promise<Tarea>;        // E-20
  desactivarTareaPropia(id: number): Promise<void>;                            // E-21
  preferencias(p: { sonidos?: boolean; tema?: PerfilPublico['tema'] }): Promise<PerfilPublico>; // E-22
  // Padre
  adminPerfiles(): Promise<PerfilAdmin[]>;                                     // E-23
  crearPerfil(p: PerfilEntrada): Promise<PerfilAdmin>;                         // E-24
  editarPerfil(id: number, p: PerfilEntrada): Promise<PerfilAdmin>;            // E-25
  desbloquearPerfil(id: number): Promise<PerfilAdmin>;                         // E-26
  borrarPerfil(id: number, definitivo: boolean): Promise<void>;               // E-27
  adminTareas(f?: { perfil_id?: number; activa?: boolean }): Promise<Tarea[]>; // E-28
  crearTarea(t: TareaEntrada): Promise<Tarea>;                                 // E-29
  editarTarea(id: number, t: TareaEntrada): Promise<Tarea>;                    // E-30
  desactivarTarea(id: number): Promise<void>;                                  // E-31
  categorias(): Promise<Categoria[]>;                                          // E-32
  aprobaciones(): Promise<InstanciaRevision[]>;                                // E-33
  revisar(id: number, decision: 'aprobada' | 'devuelta', mensaje?: string): Promise<ResultadoRevision>; // E-34
  aprobarLote(ids: number[]): Promise<ResultadoRevision[]>;                    // E-35
  urlEvidencia(evidenciaId: number): string;                                   // E-36
  adminRecompensas(): Promise<Recompensa[]>;                                   // E-37
  crearRecompensa(r: RecompensaEntrada): Promise<Recompensa>;                  // E-38
  editarRecompensa(id: number, r: RecompensaEntrada): Promise<Recompensa>;     // E-39
  adminCanjes(estado?: EstadoCanje): Promise<Canje[]>;                         // E-40
  revisarCanje(id: number, decision: 'aprobado' | 'rechazado' | 'entregado'): Promise<Canje>; // E-41
  metas(): Promise<Meta[]>;                                                    // E-42
  crearMeta(m: MetaEntrada): Promise<Meta>;                                    // E-43
  editarMeta(id: number, m: MetaEntrada & { estado?: EstadoMeta }): Promise<Meta>; // E-44
  panel(semanaIso?: string): Promise<Panel>;                                   // E-45
  adminSemana(desde?: string, perfilId?: number): Promise<Semana>;             // E-46
  configuracion(): Promise<Configuracion>;                                     // E-47
  guardarConfiguracion(c: ConfiguracionEntrada): Promise<Configuracion>;       // E-48
}
```

E-02 (`GET /csrf`) no aparece en la interfaz: la implementación lo llama internamente antes de la primera petición mutante y reintenta una vez ante `403 CSRF`.

## 4. Implementaciones del contrato

| Implementación | Archivo | Uso |
|---|---|---|
| `HttpApiClient` | `src/web/src/api/HttpApiClient.ts` | Producción y desarrollo contra CI4 |
| `MockApiClient` | `src/web/src/api/MockApiClient.ts` | Demo de Fase 1 y pruebas de componentes, datos en memoria con la forma de `DemoSeeder` |

Ambas implementan exactamente la interfaz `ApiClient`. La selección se hace con `VITE_API_MODO=http|mock`.
