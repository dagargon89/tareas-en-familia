import type { NivelUx } from '../api/ApiClient.ts';

export type { NivelUx };

export const NIVELES: readonly NivelUx[] = ['exploradoras', 'aventureras', 'pro'];

export const NOMBRE_NIVEL: Record<NivelUx, string> = {
  exploradoras: 'Exploradoras',
  aventureras: 'Aventureras',
  pro: 'Pro',
};

/** Aplica el nivel de experiencia en <html> (doc 02 §4.3). */
export function aplicarNivel(nivel: NivelUx): void {
  document.documentElement.dataset.nivel = nivel;
}
