import type {
  ActorChat,
  ApiClient,
  ApiError,
  CambioPasswordEntrada,
  Canje,
  Categoria,
  Configuracion,
  ConfiguracionEntrada,
  UsuarioAdmin,
  UsuarioEntrada,
  Dia,
  EstadoInstalacion,
  EstadoCanje,
  EstadoMeta,
  Instancia,
  InstalacionEntrada,
  InstanciaRevision,
  MensajeAsistenteHistorial,
  Meta,
  MetaEntrada,
  Panel,
  ParticipantesChat,
  PerfilAdmin,
  PerfilEntrada,
  PerfilPublico,
  Progreso,
  Recompensa,
  RecompensaEntrada,
  RecompensaParaPerfil,
  RespuestaAsistente,
  ResultadoRevision,
  Semana,
  Sesion,
  Tarea,
  TareaEntrada,
  TareaPropiaEntrada,
  TokenChatRespuesta,
} from './ApiClient.ts';

/** Error lanzado por HttpApiClient con el cuerpo de error del doc 05 §1. */
export class ErrorApi extends Error {
  readonly status: number;
  readonly detalle: ApiError;

  constructor(status: number, detalle: ApiError) {
    super(detalle.mensaje);
    this.name = 'ErrorApi';
    this.status = status;
    this.detalle = detalle;
  }

  get codigo(): string {
    return this.detalle.codigo;
  }
}

type Metodo = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
type Consulta = Record<string, string | number | boolean | undefined>;

const HEADER_CSRF = 'X-CSRF-TOKEN';

function conConsulta(ruta: string, consulta?: Consulta): string {
  if (!consulta) return ruta;
  const params = new URLSearchParams();
  for (const [clave, valor] of Object.entries(consulta)) {
    if (valor !== undefined) params.set(clave, String(valor));
  }
  const texto = params.toString();
  return texto ? `${ruta}?${texto}` : ruta;
}

/** Implementación única de ApiClient contra la API de CodeIgniter (doc 05 §4). */
export class HttpApiClient implements ApiClient {
  private readonly base: string;
  private tokenCsrf: string | null = null;

  constructor(base = '/api/v1') {
    this.base = base;
  }

  // ===== Infraestructura =====

  private async obtenerCsrf(): Promise<string> {
    const r = await fetch(`${this.base}/csrf`, { credentials: 'same-origin' });
    const cuerpo = (await r.json()) as { token: string };
    this.tokenCsrf = cuerpo.token;
    return cuerpo.token;
  }

  private async peticion<T>(
    metodo: Metodo,
    ruta: string,
    cuerpo?: unknown,
    reintento = true,
  ): Promise<T> {
    const muta = metodo !== 'GET';
    const headers: Record<string, string> = { Accept: 'application/json' };
    let body: BodyInit | undefined;

    if (cuerpo instanceof FormData) {
      body = cuerpo;
    } else if (cuerpo !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(cuerpo);
    }
    if (muta) {
      headers[HEADER_CSRF] = this.tokenCsrf ?? (await this.obtenerCsrf());
    }

    const r = await fetch(`${this.base}${ruta}`, {
      method: metodo,
      headers,
      body,
      credentials: 'same-origin',
    });

    const nuevoToken = r.headers.get(HEADER_CSRF);
    if (nuevoToken) this.tokenCsrf = nuevoToken;

    if (r.status === 204) return undefined as T;

    const datos: unknown = r.headers.get('Content-Type')?.includes('application/json')
      ? await r.json()
      : null;

    if (!r.ok) {
      const detalle = (datos as { error?: ApiError } | null)?.error ?? {
        codigo: 'DESCONOCIDO',
        mensaje: 'Algo salió mal. Intenta de nuevo.',
      };
      if (muta && reintento && r.status === 403 && detalle.codigo === 'CSRF') {
        await this.obtenerCsrf();
        return this.peticion<T>(metodo, ruta, cuerpo, false);
      }
      throw new ErrorApi(r.status, detalle);
    }
    return datos as T;
  }

  // ===== Sistema y acceso =====

  salud() {
    return this.peticion<{ estado: 'ok'; version: string }>('GET', '/salud');
  }
  login(email: string, password: string) {
    return this.peticion<Sesion>('POST', '/auth/login', { email, password });
  }
  logout() {
    return this.peticion<void>('POST', '/auth/logout');
  }
  sesion() {
    return this.peticion<Sesion>('GET', '/sesion');
  }
  perfiles() {
    return this.peticion<PerfilPublico[]>('GET', '/perfiles');
  }
  entrarPerfil(id: number, pin: string) {
    return this.peticion<Sesion>('POST', `/perfiles/${id}/entrar`, { pin });
  }
  salirPerfil() {
    return this.peticion<Sesion>('POST', '/perfiles/salir');
  }
  entrarModoPadre(pin: string) {
    return this.peticion<Sesion>('POST', '/modo-padre', { pin });
  }
  salirModoPadre() {
    return this.peticion<Sesion>('DELETE', '/modo-padre');
  }
  estadoInstalacion() {
    return this.peticion<EstadoInstalacion>('GET', '/instalacion');
  }
  instalar(datos: InstalacionEntrada) {
    return this.peticion<Sesion>('POST', '/instalacion', datos);
  }

  // ===== Hija =====

  dia(fecha?: string) {
    return this.peticion<Dia>('GET', conConsulta('/yo/dia', { fecha }));
  }
  marcarHecha(instanciaId: number, foto?: File) {
    const form = new FormData();
    if (foto) form.append('foto', foto);
    return this.peticion<Instancia>('POST', `/yo/instancias/${instanciaId}/hecha`, form);
  }
  progreso() {
    return this.peticion<Progreso>('GET', '/yo/progreso');
  }
  recompensasPerfil() {
    return this.peticion<RecompensaParaPerfil[]>('GET', '/yo/recompensas');
  }
  solicitarCanje(recompensaId: number) {
    return this.peticion<Canje>('POST', '/yo/canjes', { recompensa_id: recompensaId });
  }
  misCanjes() {
    return this.peticion<Canje[]>('GET', '/yo/canjes');
  }
  semana(desde?: string) {
    return this.peticion<Semana>('GET', conConsulta('/yo/semana', { desde }));
  }
  misTareas() {
    return this.peticion<Tarea[]>('GET', '/yo/tareas');
  }
  crearTareaPropia(t: TareaPropiaEntrada) {
    return this.peticion<Tarea>('POST', '/yo/tareas', t);
  }
  editarTareaPropia(id: number, t: TareaPropiaEntrada) {
    return this.peticion<Tarea>('PUT', `/yo/tareas/${id}`, t);
  }
  desactivarTareaPropia(id: number) {
    return this.peticion<void>('DELETE', `/yo/tareas/${id}`);
  }
  preferencias(p: { sonidos?: boolean; tema?: PerfilPublico['tema'] }) {
    return this.peticion<PerfilPublico>('PATCH', '/yo/preferencias', p);
  }

  // ===== Padre =====

  adminPerfiles() {
    return this.peticion<PerfilAdmin[]>('GET', '/admin/perfiles');
  }
  crearPerfil(p: PerfilEntrada) {
    return this.peticion<PerfilAdmin>('POST', '/admin/perfiles', p);
  }
  editarPerfil(id: number, p: PerfilEntrada) {
    return this.peticion<PerfilAdmin>('PUT', `/admin/perfiles/${id}`, p);
  }
  desbloquearPerfil(id: number) {
    return this.peticion<PerfilAdmin>('POST', `/admin/perfiles/${id}/desbloquear`);
  }
  borrarPerfil(id: number, definitivo: boolean) {
    return this.peticion<void>('DELETE', conConsulta(`/admin/perfiles/${id}`, { definitivo }));
  }
  adminTareas(f?: { perfil_id?: number; activa?: boolean }) {
    return this.peticion<Tarea[]>('GET', conConsulta('/admin/tareas', f));
  }
  crearTarea(t: TareaEntrada) {
    return this.peticion<Tarea>('POST', '/admin/tareas', t);
  }
  editarTarea(id: number, t: TareaEntrada) {
    return this.peticion<Tarea>('PUT', `/admin/tareas/${id}`, t);
  }
  desactivarTarea(id: number) {
    return this.peticion<void>('DELETE', `/admin/tareas/${id}`);
  }
  categorias() {
    return this.peticion<Categoria[]>('GET', '/admin/categorias');
  }
  aprobaciones() {
    return this.peticion<InstanciaRevision[]>('GET', '/admin/aprobaciones');
  }
  revisar(id: number, decision: 'aprobada' | 'devuelta', mensaje?: string) {
    return this.peticion<ResultadoRevision>('POST', `/admin/instancias/${id}/revision`, {
      decision,
      mensaje,
    });
  }
  aprobarLote(ids: number[]) {
    return this.peticion<ResultadoRevision[]>('POST', '/admin/instancias/revision-lote', { ids });
  }
  urlEvidencia(evidenciaId: number) {
    return `${this.base}/evidencias/${evidenciaId}`;
  }
  adminRecompensas() {
    return this.peticion<Recompensa[]>('GET', '/admin/recompensas');
  }
  crearRecompensa(r: RecompensaEntrada) {
    return this.peticion<Recompensa>('POST', '/admin/recompensas', r);
  }
  editarRecompensa(id: number, r: RecompensaEntrada) {
    return this.peticion<Recompensa>('PUT', `/admin/recompensas/${id}`, r);
  }
  adminCanjes(estado?: EstadoCanje) {
    return this.peticion<Canje[]>('GET', conConsulta('/admin/canjes', { estado }));
  }
  revisarCanje(id: number, decision: 'aprobado' | 'rechazado' | 'entregado') {
    return this.peticion<Canje>('POST', `/admin/canjes/${id}/revision`, { decision });
  }
  metas() {
    return this.peticion<Meta[]>('GET', '/admin/metas');
  }
  crearMeta(m: MetaEntrada) {
    return this.peticion<Meta>('POST', '/admin/metas', m);
  }
  editarMeta(id: number, m: MetaEntrada & { estado?: EstadoMeta }) {
    return this.peticion<Meta>('PUT', `/admin/metas/${id}`, m);
  }
  panel(semanaIso?: string) {
    return this.peticion<Panel>('GET', conConsulta('/admin/panel', { semana: semanaIso }));
  }
  adminSemana(desde?: string, perfilId?: number) {
    return this.peticion<Semana>(
      'GET',
      conConsulta('/admin/semana', { desde, perfil_id: perfilId }),
    );
  }
  configuracion() {
    return this.peticion<Configuracion>('GET', '/admin/configuracion');
  }
  guardarConfiguracion(c: ConfiguracionEntrada) {
    return this.peticion<Configuracion>('PUT', '/admin/configuracion', c);
  }
  adminUsuarios() {
    return this.peticion<UsuarioAdmin[]>('GET', '/admin/usuarios');
  }
  crearUsuario(u: UsuarioEntrada) {
    return this.peticion<UsuarioAdmin>('POST', '/admin/usuarios', u);
  }
  cambiarPassword(usuarioId: number, datos: CambioPasswordEntrada) {
    return this.peticion<UsuarioAdmin>('PUT', `/admin/usuarios/${usuarioId}/password`, datos);
  }
  participantesChat() {
    return this.peticion<ParticipantesChat>('GET', '/chat/participantes');
  }
  tokenChat() {
    return this.peticion<TokenChatRespuesta>('POST', '/chat/token');
  }
  preguntarAsistente(mensaje: string, historial?: MensajeAsistenteHistorial[]) {
    return this.peticion<RespuestaAsistente>('POST', '/asistente/mensaje', { mensaje, historial });
  }
}

export type { ActorChat };

/** Instancia compartida por toda la SPA. */
export const api: ApiClient = new HttpApiClient();
