import { useEffect, useState } from 'react';
import { api } from '../api/HttpApiClient.ts';

/**
 * Suscripción ligera al conteo total de salas con mensajes sin leer (ADR-011).
 * Importa el SDK de Firebase de forma diferida (`import()`) para no añadir bytes al bundle inicial.
 */
export function useNoLeidosChat(claveActor: string | null): number {
  const [total, setTotal] = useState(0);

  useEffect(() => {
    if (!claveActor) {
      setTotal(0);
      return;
    }

    let cancelado = false;
    let desuscribir: (() => void) | null = null;

    async function iniciar() {
      try {
        const [sesionChat, fb] = await Promise.all([api.tokenChat(), import('../lib/firebase.ts')]);
        if (cancelado) return;
        const db = await fb.conectarFirebaseChat(sesionChat);
        if (cancelado) return;

        desuscribir = fb.suscribirSalas(
          db,
          sesionChat.actor,
          (salas) => {
            if (cancelado) return;
            const pendientes = salas.filter(
              (s) =>
                s.participantes.includes(sesionChat.actor.actor_id) &&
                fb.tieneNoLeidos(s, sesionChat.actor.actor_id),
            ).length;
            setTotal(pendientes);
          },
          () => {
            if (!cancelado) setTotal(0);
          },
        );
      } catch {
        // Si el chat/emulador no está disponible, el indicador simplemente queda en 0.
        if (!cancelado) setTotal(0);
      }
    }

    void iniciar();

    return () => {
      cancelado = true;
      desuscribir?.();
    };
  }, [claveActor]);

  return total;
}
