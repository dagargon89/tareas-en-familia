import type { InstalacionEntrada } from '../../api/ApiClient.ts';
import { problemaPin } from '../../lib/pin.ts';

/** Reglas de RF-20 y doc 04 §3.1; el servidor vuelve a validar. */
export type ErroresInstalacion = Partial<Record<keyof InstalacionEntrada | 'confirmar', string>>;

export function validarInstalacion(d: InstalacionEntrada & { confirmar: string }, requiereToken: boolean): ErroresInstalacion {
  const e: ErroresInstalacion = {};
  if (!d.nombre.trim()) e.nombre = 'Escribe tu nombre';
  else if (d.nombre.trim().length > 60) e.nombre = 'Máximo 60 caracteres';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email.trim())) e.email = 'Escribe un correo válido';
  if (d.password.length < 10) e.password = 'Mínimo 10 caracteres';
  if (d.confirmar !== d.password) e.confirmar = 'Las contraseñas no coinciden';
  const pin = problemaPin(d.pin);
  if (pin) e.pin = pin;
  if (requiereToken && !d.token?.trim()) e.token = 'Escribe el token de instalación';
  return e;
}
