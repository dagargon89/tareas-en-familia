import { useSyncExternalStore } from 'react';

/** Ancho desde el que el calendario se muestra como tablero (Tailwind `lg`). */
export const ESCRITORIO = '(min-width: 1024px)';

/**
 * `true` mientras la media query se cumple; se actualiza al girar o cambiar el tamaño de la ventana.
 * Sin `matchMedia` (jsdom) devuelve `false`.
 */
export function useMediaQuery(consulta: string): boolean {
  return useSyncExternalStore(
    (avisar) => {
      const mq = window.matchMedia?.(consulta);
      mq?.addEventListener?.('change', avisar);
      return () => mq?.removeEventListener?.('change', avisar);
    },
    () => window.matchMedia?.(consulta)?.matches === true,
    () => false,
  );
}
