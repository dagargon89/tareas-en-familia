import type { UsuarioEntrada } from '../../api/ApiClient.ts';
import { problemaPin } from '../../lib/pin.ts';

/** Borrador del formulario de alta del otro padre (ADR-009). */
export interface PadreBorrador extends UsuarioEntrada {
  confirmar: string;
  confirmarPin: string;
}

export type ErroresPadre = Partial<Record<keyof PadreBorrador, string>>;

export const PADRE_VACIO: PadreBorrador = { nombre: '', email: '', password: '', confirmar: '', pin: '', confirmarPin: '' };

/** Mismas reglas que la instalación (RF-20); el servidor vuelve a validar (E-52). */
export function validarPadre(d: PadreBorrador): ErroresPadre {
  const e: ErroresPadre = {};
  if (!d.nombre.trim()) e.nombre = 'Escribe su nombre';
  else if (d.nombre.trim().length > 60) e.nombre = 'Máximo 60 caracteres';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email.trim())) e.email = 'Escribe un correo válido';
  if (d.password.length < 10) e.password = 'Mínimo 10 caracteres';
  if (d.confirmar !== d.password) e.confirmar = 'Las contraseñas no coinciden';
  const pin = problemaPin(d.pin);
  if (pin) e.pin = pin;
  else if (d.confirmarPin !== d.pin) e.confirmarPin = 'Los PIN no coinciden';
  return e;
}

export function aEntradaPadre(d: PadreBorrador): UsuarioEntrada {
  return { nombre: d.nombre.trim(), email: d.email.trim(), password: d.password, pin: d.pin };
}

/** Borrador del cambio de contraseña (ADR-010): la actual solo se pide para la propia. */
export interface PasswordBorrador {
  actual: string;
  nueva: string;
  confirmar: string;
}

export type ErroresPassword = Partial<Record<keyof PasswordBorrador, string>>;

export const PASSWORD_VACIO: PasswordBorrador = { actual: '', nueva: '', confirmar: '' };

export function validarPassword(d: PasswordBorrador, propia: boolean): ErroresPassword {
  const e: ErroresPassword = {};
  if (propia && !d.actual) e.actual = 'Escribe tu contraseña actual';
  if (d.nueva.length < 10) e.nueva = 'Mínimo 10 caracteres';
  if (d.confirmar !== d.nueva) e.confirmar = 'Las contraseñas no coinciden';
  return e;
}
