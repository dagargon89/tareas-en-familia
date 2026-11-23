// NivelUx se moverá a src/api/ApiClient.ts (doc 05 §3) en T-F1-02 y se importará desde ahí.
export type NivelUx = 'exploradoras' | 'aventureras' | 'pro';

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
