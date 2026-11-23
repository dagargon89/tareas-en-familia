// Contrato único de la API (doc 05 §3). Copia literal del documento: cualquier cambio
// se hace primero en docs/05-contrato/05_contrato_api.md. CONGELADO el 2026-11-23 (ADR-008):
// cambiarlo requiere un ADR de cambio de contrato.

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

export interface EstadoInstalacion { requerida: boolean; requiere_token: boolean; }
export interface InstalacionEntrada { nombre: string; email: string; password: string; pin: string; token?: string; }

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
export type TareaPropiaEntrada = Omit<TareaEntrada, 'perfil_id' | 'puntos' | 'categoria_id'> & { categoria_id?: number };
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
  evidencia_dias_retencion: number; puntos_tarea_propia: number; }
export interface ConfiguracionEntrada { racha_umbral?: number; sonidos_default?: boolean; pin_padre?: string; } // pin_padre cambia el PIN del usuario de la sesión
export interface UsuarioAdmin { id: number; nombre: string; email: string; es_actual: boolean; } // es_actual: el padre de esta sesión (ADR-009)
export interface UsuarioEntrada { nombre: string; email: string; password: string; pin: string; }      // ADR-009
export interface CambioPasswordEntrada { password: string; password_actual?: string; }                  // ADR-010: la actual solo para la propia

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
  estadoInstalacion(): Promise<EstadoInstalacion>;                             // E-49
  instalar(datos: InstalacionEntrada): Promise<Sesion>;                        // E-50
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
  adminUsuarios(): Promise<UsuarioAdmin[]>;                                    // E-51 (ADR-009)
  crearUsuario(u: UsuarioEntrada): Promise<UsuarioAdmin>;                      // E-52 (ADR-009)
  cambiarPassword(usuarioId: number, datos: CambioPasswordEntrada): Promise<UsuarioAdmin>; // E-53 (ADR-010)
}
