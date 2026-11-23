import { KeyRound } from 'lucide-react';
import { useEffect } from 'react';
import { Navigate, useNavigate } from 'react-router';
import type { PerfilPublico } from '../../api/ApiClient.ts';
import { usePerfiles, useSesion } from '../../hooks/useSesion.ts';
import { aplicarNivel, aplicarTema } from '../../niveles/nivel.ts';
import { Cargando } from '../../ui/Cargando.tsx';
import { FormasVivas } from '../../ui/FormasVivas.tsx';
import { AvatarSelector } from './AvatarSelector.tsx';

interface VistaProps {
  perfiles: PerfilPublico[];
  onElegir: (p: PerfilPublico) => void;
  onModoPadre: () => void;
}

/** Vista del selector de perfiles (RF-01). */
export function SelectorPerfilVista({ perfiles, onElegir, onModoPadre }: VistaProps) {
  return (
    <main className="relative mx-auto flex min-h-dvh max-w-3xl flex-col gap-8 px-4 py-8 sm:px-8">
      <FormasVivas />
      <h1 className="m-0 text-center text-display">¿Quién eres?</h1>
      {perfiles.length > 0 ? (
        <AvatarSelector perfiles={perfiles} onElegir={onElegir} />
      ) : (
        <p className="m-0 text-center text-cuerpo text-tinta-600">Todavía no hay perfiles. Papá o mamá pueden crearlos en Modo padre.</p>
      )}
      <div className="mt-auto flex justify-center">
        <button
          type="button"
          onClick={onModoPadre}
          className="inline-flex min-h-11 items-center gap-2 rounded-boton px-5 text-etiqueta text-tinta-600 hover:bg-superficie"
        >
          <KeyRound className="size-4" aria-hidden="true" /> Modo padre
        </button>
      </div>
    </main>
  );
}

export function SelectorPerfil() {
  const navegar = useNavigate();
  const { data: sesion } = useSesion();
  const perfiles = usePerfiles();

  // El selector es neutral: densidad media, sin el nivel de la última hija.
  useEffect(() => {
    aplicarNivel('aventureras');
    aplicarTema('claro');
  }, []);

  if (sesion?.perfil_activo) return <Navigate to="/hoy" replace />;
  if (perfiles.isPending) return <Cargando />;

  return (
    <SelectorPerfilVista
      perfiles={perfiles.data ?? []}
      onElegir={(p) => navegar(`/entrar/${p.id}`)}
      onModoPadre={() => navegar('/modo-padre')}
    />
  );
}
