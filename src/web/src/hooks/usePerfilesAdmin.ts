import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { PerfilEntrada } from '../api/ApiClient.ts';
import { api } from '../api/HttpApiClient.ts';
import { CLAVE_PERFILES } from './useSesion.ts';

const CLAVE = ['admin', 'perfiles'] as const;

/** Perfiles en modo padre (E-23 a E-27). Toda escritura refresca también el selector. */
export function usePerfilesAdmin() {
  return useQuery({ queryKey: CLAVE, queryFn: () => api.adminPerfiles() });
}

function useInvalidar() {
  const qc = useQueryClient();
  return () => {
    void qc.invalidateQueries({ queryKey: CLAVE });
    void qc.invalidateQueries({ queryKey: CLAVE_PERFILES });
  };
}

export function useGuardarPerfil() {
  const invalidar = useInvalidar();
  return useMutation({
    mutationFn: ({ id, datos }: { id: number | null; datos: PerfilEntrada }) =>
      id === null ? api.crearPerfil(datos) : api.editarPerfil(id, datos),
    onSuccess: invalidar,
  });
}

export function useDesbloquearPerfil() {
  const invalidar = useInvalidar();
  return useMutation({ mutationFn: (id: number) => api.desbloquearPerfil(id), onSuccess: invalidar });
}

export function useBorrarPerfil() {
  const invalidar = useInvalidar();
  return useMutation({
    mutationFn: ({ id, definitivo }: { id: number; definitivo: boolean }) => api.borrarPerfil(id, definitivo),
    onSuccess: invalidar,
  });
}
