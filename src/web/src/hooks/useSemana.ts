import { useQuery } from '@tanstack/react-query';
import { api } from '../api/HttpApiClient.ts';

/** Semana de la hija (E-17, RF-14). `desde` es un lunes. */
export function useSemana(desde: string) {
  return useQuery({ queryKey: ['semana', desde], queryFn: () => api.semana(desde) });
}

/** Semana en modo padre (E-46): una hija o todas. */
export function useAdminSemana(desde: string, perfilId?: number) {
  return useQuery({ queryKey: ['semana', 'admin', desde, perfilId ?? 'todas'], queryFn: () => api.adminSemana(desde, perfilId) });
}
