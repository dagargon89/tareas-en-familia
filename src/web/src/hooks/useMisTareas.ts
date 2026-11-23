import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { TareaPropiaEntrada } from '../api/ApiClient.ts';
import { api } from '../api/HttpApiClient.ts';

const CLAVE = ['mis-tareas'] as const;

/** Tareas propias de la hija (E-18 a E-21, RF-15 y RF-19). */
export function useMisTareas() {
  return useQuery({ queryKey: CLAVE, queryFn: () => api.misTareas() });
}

function useInvalidar() {
  const qc = useQueryClient();
  return () => {
    void qc.invalidateQueries({ queryKey: CLAVE });
    // Una escolar nueva aparece en "Mi día" desde hoy hasta su entrega (RF-13).
    void qc.invalidateQueries({ queryKey: ['dia'] });
    void qc.invalidateQueries({ queryKey: ['semana'] });
  };
}

export function useGuardarTareaPropia() {
  const invalidar = useInvalidar();
  return useMutation({
    mutationFn: ({ id, datos }: { id: number | null; datos: TareaPropiaEntrada }) =>
      id === null ? api.crearTareaPropia(datos) : api.editarTareaPropia(id, datos),
    onSuccess: invalidar,
  });
}

export function useDesactivarTareaPropia() {
  const invalidar = useInvalidar();
  return useMutation({ mutationFn: (id: number) => api.desactivarTareaPropia(id), onSuccess: invalidar });
}
