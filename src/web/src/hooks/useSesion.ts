import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import type { InstalacionEntrada, PerfilPublico, Sesion } from '../api/ApiClient.ts';
import { ErrorApi, api } from '../api/HttpApiClient.ts';

export const CLAVE_SESION = ['sesion'] as const;
export const CLAVE_PERFILES = ['perfiles'] as const;

const SIN_SESION: Sesion = { usuario: null, perfil_activo: null, modo_padre_hasta: null };

/**
 * Sesión del dispositivo (E-05). Un 401 SIN_SESION no es error: significa que nadie
 * ha iniciado sesión en este dispositivo y se devuelve una sesión vacía.
 */
export const opcionesSesion = queryOptions({
  queryKey: CLAVE_SESION,
  queryFn: async (): Promise<Sesion> => {
    try {
      return await api.sesion();
    } catch (e) {
      if (e instanceof ErrorApi && e.status === 401) return SIN_SESION;
      throw e;
    }
  },
  staleTime: 60_000,
});

/** @param refrescarCadaMs refresco periódico (el modo padre lo usa para ver la renovación). */
export function useSesion(refrescarCadaMs?: number) {
  return useQuery({ ...opcionesSesion, refetchInterval: refrescarCadaMs });
}

/** Mutación que devuelve una Sesion y la deja en caché (login, entrar, salir, modo padre). */
function useMutacionSesion<V>(fn: (v: V) => Promise<Sesion>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (sesion) => {
      qc.setQueryData(CLAVE_SESION, sesion);
    },
  });
}

export const useLogin = () =>
  useMutacionSesion(({ email, password }: { email: string; password: string }) => api.login(email, password));

export const useEntrarPerfil = () =>
  useMutacionSesion(({ id, pin }: { id: number; pin: string }) => api.entrarPerfil(id, pin));

export const useSalirPerfil = () => useMutacionSesion(() => api.salirPerfil());

export const useEntrarModoPadre = () => useMutacionSesion((pin: string) => api.entrarModoPadre(pin));

export const useSalirModoPadre = () => useMutacionSesion(() => api.salirModoPadre());

export function useLogout() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => api.logout(),
    onSuccess: () => {
      qc.clear();
      qc.setQueryData(CLAVE_SESION, SIN_SESION);
    },
  });
}

export function usePerfiles(habilitado = true) {
  return useQuery({ queryKey: CLAVE_PERFILES, queryFn: () => api.perfiles(), enabled: habilitado });
}

/** true mientras `modo_padre_hasta` esté en el futuro; se reevalúa cada 15 s. */
export function useModoPadreVigente(sesion: Sesion | undefined): boolean {
  const hasta = sesion?.modo_padre_hasta ?? null;
  const [ahora, setAhora] = useState(() => Date.now());
  useEffect(() => {
    if (!hasta) return;
    const t = window.setInterval(() => setAhora(Date.now()), 15_000);
    return () => window.clearInterval(t);
  }, [hasta]);
  return hasta !== null && new Date(hasta).getTime() > ahora;
}

export const CLAVE_INSTALACION = ['instalacion'] as const;

/**
 * Estado de la instalación inicial (E-49, RF-20). Si la API no responde se asume que no
 * hace falta instalar, para que el login siga visible.
 */
export function useEstadoInstalacion() {
  return useQuery({
    queryKey: CLAVE_INSTALACION,
    queryFn: async () => {
      try {
        return await api.estadoInstalacion();
      } catch {
        return { requerida: false, requiere_token: false };
      }
    },
    staleTime: 5 * 60_000,
  });
}

export function useInstalar() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (datos: InstalacionEntrada) => api.instalar(datos),
    onSuccess: (sesion) => {
      qc.setQueryData(CLAVE_SESION, sesion);
      qc.setQueryData(CLAVE_INSTALACION, { requerida: false, requiere_token: false });
    },
  });
}

/** Preferencias de la hija (E-22, RF-18): actualiza el perfil activo en la sesión cacheada. */
export function usePreferencias() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (p: { sonidos?: boolean; tema?: PerfilPublico['tema'] }) => api.preferencias(p),
    onSuccess: (perfil) => {
      qc.setQueryData<Sesion>(CLAVE_SESION, (s) => (s ? { ...s, perfil_activo: perfil } : s));
      void qc.invalidateQueries({ queryKey: CLAVE_PERFILES });
    },
  });
}
