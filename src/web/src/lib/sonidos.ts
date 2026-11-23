import { Howl } from 'howler';

/** Sonidos cortos del doc 08 §5, servidos desde el propio dominio (RNF-06). */
export type Sonido = 'completar' | 'nivel' | 'insignia' | 'canje';

const cache = new Map<Sonido, Howl>();

function cargar(nombre: Sonido): Howl {
  let h = cache.get(nombre);
  if (!h) {
    h = new Howl({ src: [`${import.meta.env.BASE_URL}sonidos/${nombre}.wav`], volume: 0.6, preload: true });
    cache.set(nombre, h);
  }
  return h;
}

/** Reproduce un sonido si el perfil los tiene activos. Nunca lanza: el sonido es decorativo. */
export function sonar(nombre: Sonido, activos: boolean): void {
  if (!activos) return;
  try {
    cargar(nombre).play();
  } catch {
    // Sin audio disponible (navegador o permisos): la celebración visual basta.
  }
}
