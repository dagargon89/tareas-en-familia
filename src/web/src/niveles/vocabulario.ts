import type { NivelUx } from '../api/ApiClient.ts';

/** Textos por nivel (doc 08 §3 y §6). Ningún texto de las hijas usa tono de error. */
export interface Vocabulario {
  tareas: string;
  puntos: string;
  unidadPuntos: string;
  listo: string;
  hecha: string;
  aprobada: string;
  devuelta: (mensaje: string) => string;
  pinIncorrecto: string;
  bloqueado: string;
  diaVacio: string;
  faltanPara: (faltan: number, nivel: string) => string;
  racha: (semanas: number) => string;
  rachaCero: string;
  yaTeAlcanza: string;
  teFaltan: (n: number) => string;
}

const BASE_NINAS = {
  listo: '¡Listo!',
  pinIncorrecto: 'Uy, ese no es. Intenta otra vez',
  bloqueado: 'Descansa un ratito y vuelve en 15 minutos',
  diaVacio: '¡Hoy no hay misiones! Día libre 🎈',
  rachaCero: '¡Empieza tu racha!',
  yaTeAlcanza: '¡Ya te alcanza!',
};

export const VOCABULARIO: Record<NivelUx, Vocabulario> = {
  exploradoras: {
    ...BASE_NINAS,
    tareas: 'Misiones',
    puntos: 'Estrellas',
    unidadPuntos: '⭐',
    hecha: '¡Lo lograste! ⭐ Ahora papá la revisa',
    aprobada: '¡Súper! Ya tienes tus estrellas',
    devuelta: (m) => `Casi. Papá dice: ${m}`,
    faltanPara: (n, nivel) => `Te faltan ${n} ⭐ para ${nivel}`,
    racha: (s) => `Racha: ${s} ${s === 1 ? 'semana' : 'semanas'}`,
    teFaltan: (n) => `Te faltan ${n} ⭐`,
  },
  aventureras: {
    ...BASE_NINAS,
    tareas: 'Misiones',
    puntos: 'XP',
    unidadPuntos: 'XP',
    hecha: '¡Lo lograste! Esperando aprobación',
    aprobada: 'Aprobada',
    devuelta: (m) => `Casi. Papá dice: ${m}`,
    faltanPara: (n, nivel) => `Te faltan ${n} XP para ${nivel}`,
    racha: (s) => `Racha: ${s} ${s === 1 ? 'semana' : 'semanas'}`,
    teFaltan: (n) => `Te faltan ${n} XP`,
  },
  pro: {
    tareas: 'Tareas',
    puntos: 'Puntos',
    unidadPuntos: 'pts',
    listo: 'Listo',
    hecha: 'Listo. Pendiente de aprobación',
    aprobada: 'Aprobada',
    devuelta: (m) => `Devuelta: ${m}`,
    pinIncorrecto: 'PIN incorrecto',
    bloqueado: 'Bloqueado 15 min',
    diaVacio: 'Sin tareas hoy',
    faltanPara: (n, nivel) => `${n} pts para ${nivel}`,
    racha: (s) => `Racha: ${s} sem`,
    rachaCero: 'Sin racha aún',
    yaTeAlcanza: 'Disponible',
    teFaltan: (n) => `Faltan ${n} pts`,
  },
};
