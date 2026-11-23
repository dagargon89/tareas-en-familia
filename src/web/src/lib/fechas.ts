/** "Hoy" y formatos de fecha en la zona de la app (RN-06). */
export const ZONA = 'America/Ciudad_Juarez';

/** Fecha local YYYY-MM-DD en Ciudad Juárez, sin importar la zona del dispositivo. */
export function fechaLocal(fecha: Date = new Date()): string {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: ZONA,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(fecha);
}

export function minutosRestantes(hastaIso: string, ahora: Date = new Date()): number {
  const ms = new Date(hastaIso).getTime() - ahora.getTime();
  return ms > 0 ? Math.ceil(ms / 60000) : 0;
}

/** "lunes 23 de noviembre" a partir de YYYY-MM-DD. */
export function fechaLarga(ymd: string): string {
  const [a, m, d] = ymd.split('-').map(Number);
  return new Intl.DateTimeFormat('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    timeZone: 'UTC',
  }).format(new Date(Date.UTC(a, m - 1, d)));
}

/** "23 nov" a partir de YYYY-MM-DD. */
export function fechaCorta(ymd: string): string {
  const [a, m, d] = ymd.split('-').map(Number);
  return new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', timeZone: 'UTC' })
    .format(new Date(Date.UTC(a, m - 1, d)))
    .replace('.', '');
}

/** Suma días a una fecha YYYY-MM-DD sin pasar por la zona del dispositivo. */
export function sumarDias(ymd: string, dias: number): string {
  const [a, m, d] = ymd.split('-').map(Number);
  const f = new Date(Date.UTC(a, m - 1, d + dias));
  return f.toISOString().slice(0, 10);
}

/** Diferencia en días entre dos fechas YYYY-MM-DD (b - a). */
export function diasEntre(a: string, b: string): number {
  const ms = (s: string) => {
    const [y, m, d] = s.split('-').map(Number);
    return Date.UTC(y, m - 1, d);
  };
  return Math.round((ms(b) - ms(a)) / 86_400_000);
}

/** Lunes de la semana ISO que contiene la fecha YYYY-MM-DD. */
export function lunesDe(ymd: string): string {
  const [a, m, d] = ymd.split('-').map(Number);
  const dia = new Date(Date.UTC(a, m - 1, d)).getUTCDay(); // 0 = domingo
  return sumarDias(ymd, dia === 0 ? -6 : 1 - dia);
}

/** "lun 23" a partir de YYYY-MM-DD. */
export function diaCorto(ymd: string): { semana: string; numero: number } {
  const [a, m, d] = ymd.split('-').map(Number);
  const semana = new Intl.DateTimeFormat('es-MX', { weekday: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(a, m - 1, d))).replace('.', '');
  return { semana, numero: d };
}
