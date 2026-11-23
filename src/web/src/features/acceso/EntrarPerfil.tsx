import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router';
import type { PerfilPublico } from '../../api/ApiClient.ts';
import { useEntrarPerfil, usePerfiles } from '../../hooks/useSesion.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { detalleError } from '../../lib/errores.ts';
import { minutosRestantes } from '../../lib/fechas.ts';
import { aplicarNivel } from '../../niveles/nivel.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { Cargando } from '../../ui/Cargando.tsx';
import { FormasVivas } from '../../ui/FormasVivas.tsx';
import { PinPad } from './PinPad.tsx';

interface VistaProps {
  perfil: PerfilPublico;
  onPin: (pin: string) => void;
  onVolver: () => void;
  ocupado: boolean;
  fallos: number;
  error: string | null;
  bloqueado: string | null;
}

/** Vista del PIN de una hija (RF-01) con el tamaño y vocabulario de su nivel. */
export function EntrarPerfilVista({ perfil, onPin, onVolver, ocupado, fallos, error, bloqueado }: VistaProps) {
  const Avatar = avatarDe(perfil.avatar);
  return (
    <main className="relative mx-auto flex min-h-dvh max-w-md flex-col gap-6 px-4 py-6">
      <FormasVivas />
      <button type="button" onClick={onVolver} className="inline-flex min-h-11 items-center gap-2 self-start rounded-boton px-3 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
        <ArrowLeft className="size-5" aria-hidden="true" /> Volver
      </button>
      <div className="flex flex-col items-center gap-3">
        <span className={`flex size-28 items-center justify-center rounded-full ${FONDO_COLOR[perfil.color]}`}>
          <Avatar className="size-14" aria-hidden="true" />
        </span>
      </div>
      <PinPad titulo={`Hola, ${perfil.apodo}. Escribe tu PIN`} onCompleto={onPin} ocupado={ocupado} fallos={fallos} error={error} bloqueado={bloqueado} />
    </main>
  );
}

export function EntrarPerfil() {
  const { id } = useParams();
  const navegar = useNavigate();
  const perfiles = usePerfiles();
  const entrar = useEntrarPerfil();
  const [fallos, setFallos] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [bloqueadoHasta, setBloqueadoHasta] = useState<string | null>(null);
  const [, setTick] = useState(0);

  const perfil = perfiles.data?.find((p) => p.id === Number(id));

  // El nivel de la hija se aplica desde el PIN para que el teclado tenga su tamaño.
  useEffect(() => {
    if (perfil) aplicarNivel(perfil.nivel_ux);
  }, [perfil]);

  const hasta = bloqueadoHasta ?? perfil?.bloqueado_hasta ?? null;
  const minutos = hasta ? minutosRestantes(hasta) : 0;

  useEffect(() => {
    if (minutos <= 0) return;
    const t = window.setInterval(() => setTick((n) => n + 1), 30_000);
    return () => window.clearInterval(t);
  }, [minutos]);

  if (perfiles.isPending) return <Cargando />;
  if (!perfil) return <Navigate to="/" replace />;

  const v = VOCABULARIO[perfil.nivel_ux];

  return (
    <EntrarPerfilVista
      perfil={perfil}
      ocupado={entrar.isPending}
      fallos={fallos}
      error={error}
      bloqueado={minutos > 0 ? v.bloqueado : null}
      onVolver={() => navegar('/')}
      onPin={(pin) =>
        entrar.mutate(
          { id: perfil.id, pin },
          {
            onSuccess: () => navegar('/hoy', { replace: true }),
            onError: (e) => {
              const d = detalleError(e);
              if (d.codigo === 'BLOQUEADO' && d.bloqueado_hasta) {
                setBloqueadoHasta(d.bloqueado_hasta);
                setError(null);
              } else if (d.codigo === 'PIN_INCORRECTO') {
                setError(v.pinIncorrecto);
              } else {
                setError(d.mensaje);
              }
              setFallos((f) => f + 1);
            },
          },
        )
      }
    />
  );
}
