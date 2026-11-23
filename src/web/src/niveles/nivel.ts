import type { NivelUx } from '../api/ApiClient.ts';

export type { NivelUx };

export const NIVELES: readonly NivelUx[] = ['exploradoras', 'aventureras', 'pro'];

export const NOMBRE_NIVEL: Record<NivelUx, string> = {
  exploradoras: 'Exploradoras',
  aventureras: 'Aventureras',
  pro: 'Pro',
};

/**
 * Aplica el tema en <html>. `sistema` sigue al dispositivo. El CSS ignora el modo oscuro en
 * Exploradoras (doc 08 §2.1), así que aquí no hace falta revisar el nivel.
 */
export function aplicarTema(tema: 'claro' | 'oscuro' | 'sistema'): void {
  const oscuro = tema === 'oscuro' || (tema === 'sistema' && window.matchMedia?.('(prefers-color-scheme: dark)').matches === true);
  document.documentElement.dataset.tema = oscuro ? 'oscuro' : 'claro';
}

/** Aplica el nivel de experiencia en <html> (doc 02 §4.3). */
export function aplicarNivel(nivel: NivelUx): void {
  document.documentElement.dataset.nivel = nivel;
}
