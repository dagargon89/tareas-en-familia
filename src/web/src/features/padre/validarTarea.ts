import type { DiaSemana, Frecuencia, Tarea, TareaEntrada, TipoTarea } from '../../api/ApiClient.ts';
import { fechaCorta } from '../../lib/fechas.ts';

/** Estado editable del formulario de tarea; se convierte a TareaEntrada al enviar. */
export interface BorradorTarea {
  perfil_id: number | null;
  categoria_id: number | null;
  titulo: string;
  icono: string;
  puntos: number;
  tipo: TipoTarea;
  materia: string;
  fecha_entrega: string;
  requiere_foto: boolean;
  frecuencia: Frecuencia;
  dias_semana: DiaSemana[];
  hora: string;
  fecha_inicio: string;
  fecha_fin: string;
}

export type ErroresTarea = Partial<Record<keyof BorradorTarea, string>>;

/** Reglas de RF-03 y RF-13 (el servidor vuelve a validar todo). */
export function validarTarea(b: BorradorTarea, hoy: string): ErroresTarea {
  const e: ErroresTarea = {};
  const titulo = b.titulo.trim();
  if (!titulo) e.titulo = 'Escribe un título';
  else if (titulo.length > 80) e.titulo = 'Máximo 80 caracteres';
  if (b.perfil_id === null) e.perfil_id = 'Elige a quién se asigna';
  if (b.categoria_id === null) e.categoria_id = 'Elige una categoría';

  const minimo = b.tipo === 'actividad' ? 0 : 1;
  if (!Number.isInteger(b.puntos) || b.puntos < minimo || b.puntos > 100) {
    e.puntos = b.tipo === 'actividad' ? 'Entre 0 y 100 puntos' : 'Entre 1 y 100 puntos';
  }

  if (b.tipo === 'escolar') {
    if (!b.materia.trim()) e.materia = 'Escribe la materia';
    else if (b.materia.trim().length > 40) e.materia = 'Máximo 40 caracteres';
    if (!b.fecha_entrega) e.fecha_entrega = 'Elige la fecha de entrega';
    else if (b.fecha_entrega < hoy) e.fecha_entrega = 'La entrega no puede ser antes de hoy';
    return e;
  }

  if (!b.fecha_inicio) e.fecha_inicio = 'Elige la fecha';
  if (b.frecuencia === 'semanal' && b.dias_semana.length === 0) e.dias_semana = 'Elige al menos un día';
  if (b.frecuencia !== 'una_vez' && b.fecha_fin && b.fecha_fin < b.fecha_inicio) {
    e.fecha_fin = 'El fin no puede ser antes del inicio';
  }
  return e;
}

const ORDEN_DIAS: DiaSemana[] = ['lu', 'ma', 'mi', 'ju', 'vi', 'sa', 'do'];

/**
 * Convierte el borrador a TareaEntrada. Las escolares usan `una_vez` desde su creación (doc 03 §4.1);
 * los campos que no aplican al tipo o a la frecuencia se envían como null.
 */
export function aEntrada(b: BorradorTarea, hoy: string): TareaEntrada {
  const escolar = b.tipo === 'escolar';
  const frecuencia: Frecuencia = escolar ? 'una_vez' : b.frecuencia;
  return {
    perfil_id: b.perfil_id ?? 0,
    categoria_id: b.categoria_id ?? 0,
    titulo: b.titulo.trim(),
    icono: b.icono,
    puntos: b.puntos,
    tipo: b.tipo,
    materia: escolar ? b.materia.trim() : null,
    fecha_entrega: escolar ? b.fecha_entrega : null,
    requiere_foto: b.requiere_foto,
    recurrencia: {
      frecuencia,
      dias_semana: frecuencia === 'semanal' ? ORDEN_DIAS.filter((d) => b.dias_semana.includes(d)) : null,
      hora: b.hora || null,
      // Al editar una escolar se conserva su inicio original para no mover la instancia.
      fecha_inicio: escolar ? b.fecha_inicio || hoy : b.fecha_inicio,
      fecha_fin: frecuencia === 'una_vez' ? null : b.fecha_fin || null,
    },
  };
}

const DIA_CORTO: Record<DiaSemana, string> = { lu: 'lun', ma: 'mar', mi: 'mié', ju: 'jue', vi: 'vie', sa: 'sáb', do: 'dom' };

/** Resumen legible de cuándo toca una tarea. */
export function cuandoToca(t: Tarea): string {
  const r = t.recurrencia;
  const hora = r.hora ? ` · ${r.hora}` : '';
  if (t.tipo === 'escolar' && t.fecha_entrega) return `${t.materia ?? 'Escolar'} · entrega ${fechaCorta(t.fecha_entrega)}`;
  if (r.frecuencia === 'una_vez') return `${fechaCorta(r.fecha_inicio)}${hora}`;
  if (r.frecuencia === 'diaria') return `Diaria${hora}`;
  return `${(r.dias_semana ?? []).map((d) => DIA_CORTO[d]).join(', ')}${hora}`;
}
