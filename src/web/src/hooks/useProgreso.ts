import { useQuery } from '@tanstack/react-query';
import { api } from '../api/HttpApiClient.ts';

/** Progreso de la hija: XP, nivel, saldo, racha, insignias y meta (E-13). */
export function useProgreso() {
  return useQuery({ queryKey: ['progreso'], queryFn: () => api.progreso() });
}
