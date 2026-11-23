import type { NivelUx } from '../../api/ApiClient.ts';
import { prefiereMenosMovimiento } from '../../lib/movimiento.ts';

const COLORES = ['#3F4FB8', '#FFE08A', '#C9EFD5', '#FFD7DE', '#E4E6FB'];

/**
 * Celebración al completar (doc 08 §3). Exploradoras: confeti. Aventureras y Pro celebran
 * con la propia tarjeta. Con movimiento reducido nunca hay confeti (CP-032).
 * Devuelve true si lanzó confeti.
 */
export function celebrar(nivel: NivelUx, origen?: { x: number; y: number }): boolean {
  if (nivel !== 'exploradoras' || prefiereMenosMovimiento()) return false;
  // canvas-confetti se carga solo la primera vez que una Exploradora celebra.
  void import('canvas-confetti').then(({ default: confetti }) => confetti({
    particleCount: 90,
    spread: 75,
    startVelocity: 38,
    origin: origen ?? { x: 0.5, y: 0.6 },
    colors: COLORES,
    disableForReducedMotion: true,
  }));
  return true;
}
