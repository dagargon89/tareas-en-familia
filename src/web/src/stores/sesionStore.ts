import { create } from 'zustand';
import type { NivelUx } from '../api/ApiClient.ts';
import { aplicarNivel } from '../niveles/nivel.ts';

/**
 * Estado de interfaz de la sesión (doc 02 §4.1). Los datos del servidor (sesión, perfiles)
 * viven en TanStack Query; aquí solo lo que la UI necesita al instante.
 */
interface EstadoSesion {
  nivel: NivelUx;
  sonidos: boolean;
  setNivel: (nivel: NivelUx) => void;
  setSonidos: (activos: boolean) => void;
}

export const useSesionStore = create<EstadoSesion>((set) => ({
  nivel: 'aventureras',
  sonidos: true,
  setNivel: (nivel) => {
    aplicarNivel(nivel);
    set({ nivel });
  },
  setSonidos: (sonidos) => set({ sonidos }),
}));
