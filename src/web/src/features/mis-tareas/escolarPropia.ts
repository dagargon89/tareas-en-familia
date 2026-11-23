import type { NivelUx, Tarea, TareaPropiaEntrada } from '../../api/ApiClient.ts';

/** Niveles que pueden crear tareas propias (RF-19: Aventureras y Pro; Exploradoras no). */
export function puedeCrearTareas(nivel: NivelUx): boolean {
  return nivel === 'aventureras' || nivel === 'pro';
}

/** Materias sugeridas; la materia es texto libre de hasta 40 caracteres (RF-13). */
export const MATERIAS_SUGERIDAS = ['Español', 'Matemáticas', 'Ciencias', 'Inglés', 'Historia', 'Geografía', 'Arte', 'Ed. física'] as const;

/** Íconos escolares del catálogo (doc 08 §5). */
export const ICONOS_ESCOLARES = ['libro', 'lapiz', 'calculadora', 'ciencia', 'mundo', 'idiomas', 'arte', 'musica', 'mochila', 'rompecabezas'] as const;

export interface BorradorEscolar {
  titulo: string;
  materia: string;
  fecha_entrega: string;
  icono: string;
  hora: string;
  /** Inicio original al editar; vacío en una tarea nueva (se usa hoy). */
  fecha_inicio: string;
}

export type ErroresEscolar = Partial<Record<keyof BorradorEscolar, string>>;

export function borradorEscolarDe(t: Tarea | null): BorradorEscolar {
  return {
    titulo: t?.titulo ?? '',
    materia: t?.materia ?? '',
    fecha_entrega: t?.fecha_entrega ?? '',
    icono: t?.icono ?? 'libro',
    hora: t?.recurrencia.hora ?? '',
    fecha_inicio: t?.recurrencia.fecha_inicio ?? '',
  };
}

export function validarEscolar(b: BorradorEscolar, hoy: string): ErroresEscolar {
  const e: ErroresEscolar = {};
  const titulo = b.titulo.trim();
  if (!titulo) e.titulo = '¿Qué te dejaron de tarea?';
  else if (titulo.length > 80) e.titulo = 'Que sea más corto (máximo 80)';
  const materia = b.materia.trim();
  if (!materia) e.materia = '¿De qué materia es?';
  else if (materia.length > 40) e.materia = 'Máximo 40 letras';
  if (!b.fecha_entrega) e.fecha_entrega = '¿Para cuándo es?';
  else if (b.fecha_entrega < hoy) e.fecha_entrega = 'La entrega no puede ser antes de hoy';
  return e;
}

/**
 * Entrada para E-19/E-20. Nunca lleva puntos, perfil ni categoría: el servidor los fija
 * (RF-19). Siempre es escolar, de una vez, desde su creación.
 */
export function aEntradaEscolar(b: BorradorEscolar, hoy: string): TareaPropiaEntrada {
  return {
    titulo: b.titulo.trim(),
    icono: b.icono,
    tipo: 'escolar',
    materia: b.materia.trim(),
    fecha_entrega: b.fecha_entrega,
    requiere_foto: false,
    recurrencia: { frecuencia: 'una_vez', dias_semana: null, hora: b.hora || null, fecha_inicio: b.fecha_inicio || hoy, fecha_fin: null },
  };
}
