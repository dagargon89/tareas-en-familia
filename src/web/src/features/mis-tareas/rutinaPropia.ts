import type { DiaSemana, Frecuencia, Tarea, TareaPropiaEntrada } from '../../api/ApiClient.ts';

/** Tarea propia no escolar de una Pro (RF-15): casa o actividad, con recurrencia. */
export interface BorradorRutina {
  tipo: 'casa' | 'actividad';
  titulo: string;
  icono: string;
  frecuencia: Frecuencia;
  dias_semana: DiaSemana[];
  hora: string;
  fecha_inicio: string;
  fecha_fin: string;
}

export type ErroresRutina = Partial<Record<keyof BorradorRutina, string>>;

const ORDEN: DiaSemana[] = ['lu', 'ma', 'mi', 'ju', 'vi', 'sa', 'do'];

export function borradorRutinaDe(t: Tarea | null, hoy: string): BorradorRutina {
  return {
    tipo: t?.tipo === 'actividad' ? 'actividad' : 'casa',
    titulo: t?.titulo ?? '',
    icono: t?.icono ?? 'brillo',
    frecuencia: t?.recurrencia.frecuencia ?? 'diaria',
    dias_semana: t?.recurrencia.dias_semana ?? [],
    hora: t?.recurrencia.hora ?? '',
    fecha_inicio: t?.recurrencia.fecha_inicio ?? hoy,
    fecha_fin: t?.recurrencia.fecha_fin ?? '',
  };
}

export function validarRutina(b: BorradorRutina): ErroresRutina {
  const e: ErroresRutina = {};
  const titulo = b.titulo.trim();
  if (!titulo) e.titulo = 'Escribe la tarea';
  else if (titulo.length > 80) e.titulo = 'Máximo 80 caracteres';
  if (!b.fecha_inicio) e.fecha_inicio = 'Elige la fecha';
  if (b.frecuencia === 'semanal' && b.dias_semana.length === 0) e.dias_semana = 'Elige al menos un día';
  if (b.frecuencia !== 'una_vez' && b.fecha_fin && b.fecha_fin < b.fecha_inicio) e.fecha_fin = 'El fin no puede ser antes del inicio';
  return e;
}

/**
 * Entrada para E-19/E-20. Sin puntos, perfil ni categoría: el servidor fija los puntos
 * (`puntos_tarea_propia`) y la categoría por tipo (casa → "Casa", actividad → "Actividad").
 */
export function aEntradaRutina(b: BorradorRutina): TareaPropiaEntrada {
  return {
    titulo: b.titulo.trim(),
    icono: b.icono,
    tipo: b.tipo,
    materia: null,
    fecha_entrega: null,
    requiere_foto: false,
    recurrencia: {
      frecuencia: b.frecuencia,
      dias_semana: b.frecuencia === 'semanal' ? ORDEN.filter((d) => b.dias_semana.includes(d)) : null,
      hora: b.hora || null,
      fecha_inicio: b.fecha_inicio,
      fecha_fin: b.frecuencia === 'una_vez' ? null : b.fecha_fin || null,
    },
  };
}
