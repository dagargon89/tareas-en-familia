import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { EstadoCanje, RecompensaEntrada } from '../api/ApiClient.ts';
import { api } from '../api/HttpApiClient.ts';

// ===== Hija (E-14 a E-16) =====

export function useRecompensasPerfil() {
  return useQuery({ queryKey: ['premios'], queryFn: () => api.recompensasPerfil() });
}

export function useMisCanjes() {
  return useQuery({ queryKey: ['mis-canjes'], queryFn: () => api.misCanjes() });
}

/** Pedir un canje reserva saldo (RF-09): se refrescan premios, canjes y progreso. */
export function useSolicitarCanje() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (recompensaId: number) => api.solicitarCanje(recompensaId),
    onSettled: () => {
      for (const clave of [['premios'], ['mis-canjes'], ['progreso']]) void qc.invalidateQueries({ queryKey: clave });
    },
  });
}

// ===== Padre (E-37 a E-41) =====

export function useRecompensasAdmin() {
  return useQuery({ queryKey: ['admin', 'recompensas'], queryFn: () => api.adminRecompensas() });
}

export function useGuardarRecompensa() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, datos }: { id: number | null; datos: RecompensaEntrada }) =>
      id === null ? api.crearRecompensa(datos) : api.editarRecompensa(id, datos),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin', 'recompensas'] });
      void qc.invalidateQueries({ queryKey: ['premios'] });
    },
  });
}

export function useCanjesAdmin(estado?: EstadoCanje) {
  return useQuery({ queryKey: ['admin', 'canjes', estado ?? 'todos'], queryFn: () => api.adminCanjes(estado), refetchInterval: 60_000 });
}

export function useRevisarCanje() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, decision }: { id: number; decision: 'aprobado' | 'rechazado' | 'entregado' }) => api.revisarCanje(id, decision),
    // También al fallar: un 409 significa que otro dispositivo ya lo revisó.
    onSettled: () => {
      for (const clave of [['admin', 'canjes'], ['admin', 'panel'], ['premios'], ['mis-canjes'], ['progreso']]) {
        void qc.invalidateQueries({ queryKey: clave });
      }
    },
  });
}
