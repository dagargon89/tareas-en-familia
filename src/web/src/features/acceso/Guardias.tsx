import { useEffect, type ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router';
import { useModoPadreVigente, useSesion } from '../../hooks/useSesion.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { Cargando } from '../../ui/Cargando.tsx';

/** Requiere sesión de dispositivo (doc 02 §4.2); si no hay, va al login del padre. */
export function RequiereSesion({ children }: { children: ReactNode }) {
  const { data, isPending } = useSesion();
  if (isPending) return <Cargando />;
  if (!data?.usuario) return <Navigate to="/login" replace />;
  return children;
}

/** Requiere perfil activo; aplica su nivel y sonidos a la interfaz. */
export function RequierePerfil({ children, soloPro = false }: { children: ReactNode; soloPro?: boolean }) {
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
  if (soloPro && perfil.nivel_ux !== 'pro') return <Navigate to="/hoy" replace />;
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
