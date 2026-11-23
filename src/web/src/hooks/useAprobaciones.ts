import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/HttpApiClient.ts';

const CLAVE = ['admin', 'aprobaciones'] as const;

/** Bandeja de instancias `hecha` de todas las hijas, la más antigua primero (E-33, RF-07). */
export function useAprobaciones() {
  return useQuery({ queryKey: CLAVE, queryFn: () => api.aprobaciones(), refetchInterval: 60_000 });
}

/** Aprobar o devolver mueve puntos, insignias y meta: se refresca todo lo que los muestra. */
function useInvalidar() {
  const qc = useQueryClient();
  return () => {
    for (const clave of [CLAVE, ['dia'], ['semana'], ['progreso'], ['admin', 'panel']]) {
      void qc.invalidateQueries({ queryKey: clave });
    }
  };
}

export function useRevisar() {
  const invalidar = useInvalidar();
  return useMutation({
    mutationFn: ({ id, decision, mensaje }: { id: number; decision: 'aprobada' | 'devuelta'; mensaje?: string }) =>
      api.revisar(id, decision, mensaje),
    // También al fallar: un 409 significa que la lista ya cambió.
    onSettled: invalidar,
  });
}

export function useAprobarLote() {
  const invalidar = useInvalidar();
  return useMutation({ mutationFn: (ids: number[]) => api.aprobarLote(ids), onSettled: invalidar });
}
