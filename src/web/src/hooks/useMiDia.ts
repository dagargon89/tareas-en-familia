import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { Dia, Instancia } from '../api/ApiClient.ts';
import { api } from '../api/HttpApiClient.ts';

export const claveDia = (fecha: string) => ['dia', fecha] as const;

/** "Mi día" de una fecha (E-11). El servidor ordena por hora y genera instancias faltantes. */
export function useMiDia(fecha: string) {
  return useQuery({ queryKey: claveDia(fecha), queryFn: () => api.dia(fecha) });
}

function recalcularResumen(instancias: Instancia[]): Dia['resumen'] {
  return {
    total: instancias.length,
    hechas: instancias.filter((i) => i.estado === 'hecha' || i.estado === 'aprobada').length,
    aprobadas: instancias.filter((i) => i.estado === 'aprobada').length,
  };
}

function reemplazar(dia: Dia, nueva: Instancia): Dia {
  const instancias = dia.instancias.map((i) => (i.id === nueva.id ? nueva : i));
  return { ...dia, instancias, resumen: recalcularResumen(instancias) };
}

/**
 * Marcar como hecha (E-12, RF-05). Actualiza la tarjeta al instante (optimista) y la
 * revierte si el servidor lo rechaza. El servidor decide el estado final: una actividad
 * de 0 puntos regresa ya `aprobada` (RF-14). Nunca se envían puntos (RN-03).
 */
export function useMarcarHecha(fecha: string) {
  const qc = useQueryClient();
  const clave = claveDia(fecha);
  return useMutation({
    mutationFn: ({ instancia, foto }: { instancia: Instancia; foto?: File }) => api.marcarHecha(instancia.id, foto),
    onMutate: async ({ instancia }) => {
      await qc.cancelQueries({ queryKey: clave });
      const previo = qc.getQueryData<Dia>(clave);
      if (previo) {
        qc.setQueryData<Dia>(clave, reemplazar(previo, { ...instancia, estado: 'hecha', mensaje_devolucion: null }));
      }
      return { previo };
    },
    onError: (_e, _v, ctx) => {
      if (ctx?.previo) qc.setQueryData(clave, ctx.previo);
    },
    onSuccess: (instancia) => {
      const actual = qc.getQueryData<Dia>(clave);
      if (actual) qc.setQueryData<Dia>(clave, reemplazar(actual, instancia));
      // Una escolar aparece en varios días (RF-13): los demás se refrescan al visitarlos.
      void qc.invalidateQueries({ queryKey: ['dia'], refetchType: 'none' });
      void qc.invalidateQueries({ queryKey: ['semana'], refetchType: 'none' });
    },
  });
}
