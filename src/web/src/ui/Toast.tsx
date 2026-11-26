import { X } from 'lucide-react';
import { useEffect } from 'react';

type Tono = 'lavanda' | 'menta';

interface Props {
  mensaje: string | null;
  tono?: Tono;
  /** Eleva el toast cuando hay barra de navegación fija abajo (vista de hijas). */
  sobreNav?: boolean;
  /** Milisegundos antes de cerrar automáticamente (0 desactiva el auto-cierre). */
  duracionMs?: number;
  onCerrar?: () => void;
}

const TONOS: Record<Tono, string> = {
  lavanda: 'bg-lavanda-100 text-indigo-700',
  menta: 'bg-menta-200 text-tinta-900',
};

/**
 * Aviso flotante visible aunque el usuario haya hecho scroll.
 * Mantiene aria-live="polite" para lectores de pantalla.
 */
export function Toast({ mensaje, tono = 'lavanda', sobreNav = false, duracionMs = 5000, onCerrar }: Props) {
  useEffect(() => {
    if (!mensaje || !onCerrar || duracionMs <= 0) return;
    const t = window.setTimeout(onCerrar, duracionMs);
    return () => window.clearTimeout(t);
  }, [mensaje, onCerrar, duracionMs]);

  return (
    <div
      aria-live="polite"
      className={`pointer-events-none fixed inset-x-4 z-30 flex justify-center ${sobreNav ? 'bottom-28' : 'bottom-6'}`}
    >
      {mensaje && (
        <div
          className={`pointer-events-auto flex max-w-lg items-center gap-3 rounded-tarjeta-interna border border-indigo-600/15 px-4 py-3 text-cuerpo ${TONOS[tono]}`}
        >
          <span className="min-w-0 flex-1">{mensaje}</span>
          {onCerrar && (
            <button
              type="button"
              onClick={onCerrar}
              aria-label="Cerrar aviso"
              className="inline-flex size-8 shrink-0 items-center justify-center rounded-full hover:bg-indigo-600/10"
            >
              <X className="size-4" aria-hidden="true" />
            </button>
          )}
        </div>
      )}
    </div>
  );
}
