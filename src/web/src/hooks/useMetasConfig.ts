import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { ConfiguracionEntrada, EstadoMeta, MetaEntrada } from '../api/ApiClient.ts';
import { api } from '../api/HttpApiClient.ts';

/** Metas familiares (E-42 a E-44, RF-12). */
export function useMetas() {
  return useQuery({ queryKey: ['admin', 'metas'], queryFn: () => api.metas() });
}

export function useGuardarMeta() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, datos }: { id: number | null; datos: MetaEntrada & { estado?: EstadoMeta } }) =>
      id === null ? api.crearMeta(datos) : api.editarMeta(id, datos),
    onSettled: () => {
      for (const clave of [['admin', 'metas'], ['admin', 'panel'], ['progreso']]) void qc.invalidateQueries({ queryKey: clave });
    },
  });
}

/** Configuración familiar (E-47, E-48, RF-18). */
export function useConfiguracion() {
  return useQuery({ queryKey: ['admin', 'configuracion'], queryFn: () => api.configuracion() });
}

export function useGuardarConfiguracion() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (c: ConfiguracionEntrada) => api.guardarConfiguracion(c),
    onSuccess: (c) => qc.setQueryData(['admin', 'configuracion'], c),
  });
}
