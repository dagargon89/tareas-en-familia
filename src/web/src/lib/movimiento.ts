/** true si el sistema pide movimiento reducido (RNF-04, doc 08 §2.4). */
export function prefiereMenosMovimiento(): boolean {
  return typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}
