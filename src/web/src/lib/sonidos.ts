import type { Howl } from 'howler';

/** Sonidos cortos del doc 08 §5, servidos desde el propio dominio (RNF-06). */
export type Sonido = 'completar' | 'nivel' | 'insignia' | 'canje';

const cache = new Map<Sonido, Howl>();

// Howler se carga la primera vez que algo suena, no con la app (el PWA ya lo tiene en caché).
let howler: Promise<typeof import('howler')> | null = null;

/** Reproduce un sonido si el perfil los tiene activos. Nunca lanza: el sonido es decorativo. */
export function sonar(nombre: Sonido, activos: boolean): void {
  if (!activos) return;
  howler ??= import('howler');
  howler
    .then(({ Howl }) => {
      let h = cache.get(nombre);
      if (!h) {
        h = new Howl({ src: [`${import.meta.env.BASE_URL}sonidos/${nombre}.wav`], volume: 0.6, preload: true });
        cache.set(nombre, h);
      }
      h.play();
    })
    .catch(() => {
      // Sin audio disponible (navegador, permisos o sin red): la celebración visual basta.
    });
}
