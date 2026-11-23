import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { UsuarioEntrada } from '../api/ApiClient.ts';
import { api } from '../api/HttpApiClient.ts';

const CLAVE = ['admin', 'usuarios'];

/** Padres de la familia (E-51, ADR-009). */
export function useUsuarios() {
  return useQuery({ queryKey: CLAVE, queryFn: () => api.adminUsuarios() });
}

/** Alta del otro padre (E-52, ADR-009). */
export function useCrearUsuario() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (u: UsuarioEntrada) => api.crearUsuario(u),
    onSettled: () => qc.invalidateQueries({ queryKey: CLAVE }),
  });
}
