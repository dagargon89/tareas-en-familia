/**
 * Datos fijos SOLO para la galería de componentes (ADR-008). No implementan ApiClient
 * ni se usan fuera de /app/galeria.
 */
import type { Categoria, Tarea, PerfilAdmin, Insignia, Instancia, InstanciaRevision, Meta, Nivel, PerfilPublico, RecompensaParaPerfil } from '../../api/ApiClient.ts';
import { fechaLocal } from '../../lib/fechas.ts';

const hoy = fechaLocal();
const enDias = (n: number) => fechaLocal(new Date(Date.now() + n * 86400000));

export const PERFILES: PerfilPublico[] = [
  { id: 1, apodo: 'Luna', avatar: 'conejo', color: 'rosa', nivel_ux: 'exploradoras', sonidos: true, tema: 'claro', bloqueado_hasta: null },
  { id: 2, apodo: 'Sofi', avatar: 'gato', color: 'menta', nivel_ux: 'aventureras', sonidos: true, tema: 'claro', bloqueado_hasta: null },
  { id: 3, apodo: 'Vale', avatar: 'luna', color: 'indigo', nivel_ux: 'pro', sonidos: false, tema: 'sistema', bloqueado_hasta: new Date(Date.now() + 9 * 60000).toISOString() },
];

const base = { perfil_id: 2, fecha: hoy, fecha_entrega: null, materia: null, requiere_foto: false, tiene_evidencia: false, evidencia_id: null, mensaje_devolucion: null, completada_en: null } as const;

export const INSTANCIAS: Instancia[] = [
  { ...base, id: 11, tarea_id: 1, hora: '07:30', titulo: 'Tender la cama', icono: 'cama', color: 'ambar', tipo: 'casa', puntos: 3, estado: 'pendiente' },
  { ...base, id: 12, tarea_id: 2, hora: '16:00', titulo: 'Leer 20 minutos', icono: 'libro', color: 'menta', tipo: 'escolar', materia: 'Español', fecha_entrega: enDias(2), puntos: 5, estado: 'pendiente' },
  { ...base, id: 13, tarea_id: 3, hora: '17:00', titulo: 'Darle de comer a Max', icono: 'perro', color: 'rosa', tipo: 'casa', puntos: 4, estado: 'devuelta', mensaje_devolucion: 'falta llenarle el agua' },
  { ...base, id: 14, tarea_id: 4, hora: '18:00', titulo: 'Clase de música', icono: 'musica', color: 'lavanda', tipo: 'actividad', puntos: 0, estado: 'pendiente' },
  { ...base, id: 15, tarea_id: 5, hora: null, titulo: 'Recoger mi cuarto', icono: 'brillo', color: 'ambar', tipo: 'casa', puntos: 6, estado: 'hecha', requiere_foto: true, tiene_evidencia: true, evidencia_id: 1 },
  { ...base, id: 16, tarea_id: 6, hora: null, titulo: 'Lavarme los dientes', icono: 'dientes', color: 'menta', tipo: 'casa', puntos: 2, estado: 'aprobada' },
];

export const NIVEL_ACTUAL: Nivel = { nombre: 'Raíz', xp_desde: 300, xp_hasta: 699 };
export const NIVEL_SIGUIENTE: Nivel = { nombre: 'Árbol', xp_desde: 700, xp_hasta: 1499 };

export const INSIGNIAS: Insignia[] = [
  { codigo: 'primera', nombre: 'Primera misión', descripcion: 'Completa tu primera misión', icono: 'brillo', color: 'ambar', obtenida_en: '2026-11-02T18:00:00-07:00' },
  { codigo: 'diez', nombre: '10 misiones', descripcion: 'Completa 10 misiones', icono: 'trofeo', color: 'menta', obtenida_en: '2026-11-10T18:00:00-07:00' },
  { codigo: 'racha2', nombre: 'Racha de 2 semanas', descripcion: 'Cumple 2 semanas seguidas', icono: 'planta', color: 'rosa', obtenida_en: null },
  { codigo: 'cincuenta', nombre: '50 misiones', descripcion: 'Completa 50 misiones', icono: 'trofeo', color: 'lavanda', obtenida_en: null },
];

export const META: Meta = { id: 1, titulo: 'Noche de pizza y película', premio: 'Pizza y peli en casa', objetivo_puntos: 400, progreso_puntos: 265, fecha_inicio: enDias(-10), fecha_limite: enDias(20), estado: 'activa' };

export const RECOMPENSAS: RecompensaParaPerfil[] = [
  { id: 1, titulo: '30 min de videojuegos', icono: 'videojuego', costo: 40, activa: true, alcanza: true, faltan: 0 },
  { id: 2, titulo: 'Helado el fin de semana', icono: 'helado', costo: 80, activa: true, alcanza: true, faltan: 0 },
  { id: 3, titulo: 'Elegir la película', icono: 'palomitas', costo: 150, activa: true, alcanza: false, faltan: 22 },
];

export const BANDEJA: InstanciaRevision[] = [
  { ...INSTANCIAS[4], perfil: PERFILES[1] },
  { ...base, id: 21, tarea_id: 7, perfil_id: 1, hora: null, titulo: 'Guardar juguetes', icono: 'rompecabezas', color: 'ambar', tipo: 'casa', puntos: 3, estado: 'hecha', perfil: PERFILES[0] },
];

export const PERFILES_ADMIN: PerfilAdmin[] = PERFILES.map((p, i) => ({ ...p, activo: true, orden: i, pin_intentos: p.bloqueado_hasta ? 5 : 0 })).concat([
  { id: 4, apodo: 'Prueba', avatar: 'nube', color: 'lavanda', nivel_ux: 'aventureras', sonidos: true, tema: 'claro', bloqueado_hasta: null, activo: false, orden: 3, pin_intentos: 0 },
]);

export const CATEGORIAS: Categoria[] = [
  { id: 1, nombre: 'Casa', icono: 'sillon', color: 'ambar' },
  { id: 2, nombre: 'Escuela', icono: 'libro', color: 'menta' },
  { id: 3, nombre: 'Actividad', icono: 'musica', color: 'lavanda' },
  { id: 4, nombre: 'Hábito', icono: 'brillo', color: 'rosa' },
  { id: 5, nombre: 'Cuidado personal', icono: 'dientes', color: 'menta' },
];

const rec = (r: Partial<Tarea['recurrencia']>): Tarea['recurrencia'] => ({ frecuencia: 'diaria', dias_semana: null, hora: null, fecha_inicio: hoy, fecha_fin: null, ...r });

export const TAREAS: Tarea[] = [
  { id: 1, perfil_id: 2, categoria_id: 1, titulo: 'Tender la cama', icono: 'cama', puntos: 3, tipo: 'casa', materia: null, fecha_entrega: null, requiere_foto: false, creada_por: 'padre', activa: true, recurrencia: rec({ hora: '07:30' }) },
  { id: 2, perfil_id: 2, categoria_id: 2, titulo: 'Leer 20 minutos', icono: 'libro', puntos: 5, tipo: 'escolar', materia: 'Español', fecha_entrega: enDias(2), requiere_foto: false, creada_por: 'padre', activa: true, recurrencia: rec({ frecuencia: 'una_vez' }) },
  { id: 3, perfil_id: 1, categoria_id: 3, titulo: 'Clase de música', icono: 'musica', puntos: 0, tipo: 'actividad', materia: null, fecha_entrega: null, requiere_foto: false, creada_por: 'padre', activa: true, recurrencia: rec({ frecuencia: 'semanal', dias_semana: ['ma', 'ju'], hora: '18:00' }) },
  { id: 4, perfil_id: 3, categoria_id: 4, titulo: 'Practicar inglés', icono: 'idiomas', puntos: 5, tipo: 'casa', materia: null, fecha_entrega: null, requiere_foto: false, creada_por: 'perfil', activa: true, recurrencia: rec({ frecuencia: 'semanal', dias_semana: ['lu', 'mi', 'vi'] }) },
  { id: 6, perfil_id: 2, categoria_id: 2, titulo: 'Maqueta del sistema solar', icono: 'ciencia', puntos: 5, tipo: 'escolar', materia: 'Ciencias', fecha_entrega: enDias(4), requiere_foto: false, creada_por: 'perfil', activa: true, recurrencia: rec({ frecuencia: 'una_vez', hora: '17:00' }) },
  { id: 7, perfil_id: 2, categoria_id: 2, titulo: 'Página 45 del libro', icono: 'calculadora', puntos: 5, tipo: 'escolar', materia: 'Matemáticas', fecha_entrega: enDias(1), requiere_foto: false, creada_por: 'perfil', activa: true, recurrencia: rec({ frecuencia: 'una_vez' }) },
  { id: 5, perfil_id: 1, categoria_id: 1, titulo: 'Recoger mi cuarto', icono: 'brillo', puntos: 6, tipo: 'casa', materia: null, fecha_entrega: null, requiere_foto: true, creada_por: 'padre', activa: true, recurrencia: rec({}) },
];
