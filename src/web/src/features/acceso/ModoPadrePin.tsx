import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { useEntrarModoPadre } from '../../hooks/useSesion.ts';
import { detalleError } from '../../lib/errores.ts';
import { minutosRestantes } from '../../lib/fechas.ts';
import { aplicarNivel } from '../../niveles/nivel.ts';
import { PinPad } from './PinPad.tsx';

/** PIN del padre para entrar a modo padre (RF-02, E-09). Dura 10 minutos deslizantes. */
export function ModoPadrePin() {
  const navegar = useNavigate();
  const { state } = useLocation() as { state: { volverA?: string } | null };
  const entrar = useEntrarModoPadre();
  const [fallos, setFallos] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [bloqueado, setBloqueado] = useState<string | null>(null);

  useEffect(() => aplicarNivel('pro'), []);

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col gap-8 px-4 py-6">
      <button type="button" onClick={() => navegar('/')} className="inline-flex min-h-11 items-center gap-2 self-start rounded-boton px-3 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
        <ArrowLeft className="size-5" aria-hidden="true" /> Volver
      </button>
      <PinPad
        titulo="Modo padre · escribe tu PIN"
        ocupado={entrar.isPending}
        fallos={fallos}
        error={error}
        bloqueado={bloqueado}
        onCompleto={(pin) =>
          entrar.mutate(pin, {
            onSuccess: () => navegar(state?.volverA ?? '/padre', { replace: true }),
            onError: (e) => {
              const d = detalleError(e);
              if (d.codigo === 'BLOQUEADO' && d.bloqueado_hasta) {
                setBloqueado(`Bloqueado ${minutosRestantes(d.bloqueado_hasta)} min por intentos fallidos`);
              } else if (d.codigo === 'PIN_INCORRECTO') {
                const r = d.intentos_restantes;
                setError(r !== undefined ? `PIN incorrecto. Te quedan ${r} intentos` : 'PIN incorrecto');
              } else {
                setError(d.mensaje);
              }
              setFallos((f) => f + 1);
            },
          })
        }
      />
    </main>
  );
}
