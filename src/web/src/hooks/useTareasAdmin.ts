import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { TareaEntrada } from '../api/ApiClient.ts';
import { api } from '../api/HttpApiClient.ts';

const CLAVE = ['admin', 'tareas'] as const;

/** Tareas en modo padre (E-28 a E-32). */
export function useTareasAdmin(filtro: { perfil_id?: number; activa?: boolean }) {
  return useQuery({ queryKey: [...CLAVE, filtro], queryFn: () => api.adminTareas(filtro) });
}

export function useCategorias() {
  return useQuery({ queryKey: ['admin', 'categorias'], queryFn: () => api.categorias(), staleTime: 10 * 60_000 });
}

function useInvalidar() {
  const qc = useQueryClient();
  return () => {
    void qc.invalidateQueries({ queryKey: CLAVE });
    // Editar o desactivar cambia instancias futuras: "Mi día" y la semana se refrescan.
    void qc.invalidateQueries({ queryKey: ['dia'] });
    void qc.invalidateQueries({ queryKey: ['semana'] });
  };
}

export function useGuardarTarea() {
  const invalidar = useInvalidar();
  return useMutation({
    mutationFn: ({ id, datos }: { id: number | null; datos: TareaEntrada }) =>
      id === null ? api.crearTarea(datos) : api.editarTarea(id, datos),
    onSuccess: invalidar,
  });
}

export function useDesactivarTarea() {
  const invalidar = useInvalidar();
  return useMutation({ mutationFn: (id: number) => api.desactivarTarea(id), onSuccess: invalidar });
}
