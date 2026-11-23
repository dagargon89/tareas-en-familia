import type { MetaEntrada, RecompensaEntrada } from '../../api/ApiClient.ts';
import { problemaPin } from '../../lib/pin.ts';

/** Costo máximo: `recompensas.costo` es SMALLINT UNSIGNED (doc 03). */
export const COSTO_MAXIMO = 9999;

export function validarRecompensa(r: RecompensaEntrada): Partial<Record<keyof RecompensaEntrada, string>> {
  const e: Partial<Record<keyof RecompensaEntrada, string>> = {};
  const titulo = r.titulo.trim();
  if (!titulo) e.titulo = 'Escribe el premio';
  else if (titulo.length > 60) e.titulo = 'Máximo 60 caracteres';
  if (!Number.isInteger(r.costo) || r.costo < 1 || r.costo > COSTO_MAXIMO) e.costo = `Entre 1 y ${COSTO_MAXIMO} puntos`;
  return e;
}

export function validarMeta(m: MetaEntrada): Partial<Record<keyof MetaEntrada, string>> {
  const e: Partial<Record<keyof MetaEntrada, string>> = {};
  if (!m.titulo.trim()) e.titulo = 'Escribe la meta';
  else if (m.titulo.trim().length > 60) e.titulo = 'Máximo 60 caracteres';
  if (!m.premio.trim()) e.premio = 'Escribe el premio';
  else if (m.premio.trim().length > 80) e.premio = 'Máximo 80 caracteres';
  if (!Number.isInteger(m.objetivo_puntos) || m.objetivo_puntos < 1) e.objetivo_puntos = 'Al menos 1 punto';
  if (!m.fecha_inicio) e.fecha_inicio = 'Elige el inicio';
  if (m.fecha_limite && m.fecha_inicio && m.fecha_limite < m.fecha_inicio) e.fecha_limite = 'No puede ser antes del inicio';
  return e;
}

export interface AjustesBorrador {
  racha_umbral: number;
  sonidos_default: boolean;
  pin: string;
  confirmar: string;
}

/** RF-18: umbral 1 a 100; el PIN solo se valida si se quiere cambiar. */
export function validarAjustes(a: AjustesBorrador): Partial<Record<keyof AjustesBorrador, string>> {
  const e: Partial<Record<keyof AjustesBorrador, string>> = {};
  if (!Number.isInteger(a.racha_umbral) || a.racha_umbral < 1 || a.racha_umbral > 100) e.racha_umbral = 'Entre 1 y 100 %';
  if (a.pin || a.confirmar) {
    const p = problemaPin(a.pin);
    if (p) e.pin = p;
    else if (a.pin !== a.confirmar) e.confirmar = 'Los PIN no coinciden';
  }
  return e;
}
