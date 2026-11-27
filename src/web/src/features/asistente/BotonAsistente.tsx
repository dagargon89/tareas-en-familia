import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import type { NivelUx } from '../../api/ApiClient.ts';
import { AsistenteDrawer } from './AsistenteDrawer.tsx';

interface BotonAsistenteProps {
  claveActor: string;
  nombreActor: string;
  nivel: NivelUx | null;
  conTexto?: boolean;
}

/**
 * Botón en el header que abre el Asistente IA de Tareas en Familia (ADR-012).
 */
export function BotonAsistente({ claveActor, nombreActor, nivel, conTexto = false }: BotonAsistenteProps) {
  const [abierto, setAbierto] = useState(false);

  return (
    <>
      <button
        type="button"
        onClick={() => setAbierto(true)}
        aria-label="Abrir asistente de ayuda"
        title="Asistente de ayuda"
        className={
          conTexto
            ? 'inline-flex min-h-tactil items-center gap-2 rounded-boton bg-lavanda-100 px-3.5 text-etiqueta text-indigo-700 transition hover:brightness-95'
            : 'inline-flex size-11 items-center justify-center rounded-full text-indigo-700 transition-colors hover:bg-lavanda-100'
        }
      >
        <Sparkles className="size-5" aria-hidden="true" />
        {conTexto && <span className="hidden sm:inline">Ayuda</span>}
      </button>
      {abierto && (
        <AsistenteDrawer
          abierto={abierto}
          onCerrar={() => setAbierto(false)}
          claveActor={claveActor}
          nombreActor={nombreActor}
          nivel={nivel}
        />
      )}
    </>
  );
}
