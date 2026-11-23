import { useEffect, type ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router';
import { useModoPadreVigente, useSesion } from '../../hooks/useSesion.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { Cargando } from '../../ui/Cargando.tsx';
import { puedeCrearTareas } from '../mis-tareas/escolarPropia.ts';

/** Requiere sesión de dispositivo (doc 02 §4.2); si no hay, va al login del padre. */
export function RequiereSesion({ children }: { children: ReactNode }) {
  const { data, isPending } = useSesion();
  if (isPending) return <Cargando />;
  if (!data?.usuario) return <Navigate to="/login" replace />;
  return children;
}

/** Requiere perfil activo; aplica su nivel y sonidos a la interfaz. */
/** `puedeCrear`: solo niveles que crean tareas propias (RF-19). */
export function RequierePerfil({ children, puedeCrear = false }: { children: ReactNode; puedeCrear?: boolean }) {
  const { data, isPending } = useSesion();
  const perfil = data?.perfil_activo ?? null;
  const { setNivel, setSonidos } = useSesionStore();

  useEffect(() => {
    if (perfil) {
      setNivel(perfil.nivel_ux);
      setSonidos(perfil.sonidos);
    }
  }, [perfil, setNivel, setSonidos]);

  if (isPending) return <Cargando />;
  if (!data?.usuario) return <Navigate to="/login" replace />;
  if (!perfil) return <Navigate to="/" replace />;
  if (puedeCrear && !puedeCrearTareas(perfil.nivel_ux)) return <Navigate to="/hoy" replace />;
  return children;
}

/** Requiere modo padre vigente; si expiró, pide el PIN y vuelve a la misma ruta. */
export function RequiereModoPadre({ children }: { children: ReactNode }) {
  const { data, isPending } = useSesion();
  const vigente = useModoPadreVigente(data);
  const { pathname } = useLocation();
  if (isPending) return <Cargando />;
  if (!data?.usuario) return <Navigate to="/login" replace />;
  if (!vigente) return <Navigate to="/modo-padre" replace state={{ volverA: pathname }} />;
  return children;
}
