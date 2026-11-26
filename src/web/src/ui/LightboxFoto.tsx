import { X } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';

interface Props {
  abierto: boolean;
  titulo: string;
  subtitulo?: string;
  src: string;
  alt: string;
  acciones?: ReactNode;
  onCerrar: () => void;
}

/** Modal ligero para ver fotos de evidencia o previsualizar antes de subir. */
export function LightboxFoto({ abierto, titulo, subtitulo, src, alt, acciones, onCerrar }: Props) {
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (abierto) {
      if (!el.open) {
        if (typeof el.showModal === 'function') {
          el.showModal();
        } else {
          el.setAttribute('open', '');
        }
      }
    } else if (el.open) {
      if (typeof el.close === 'function') {
        el.close();
      } else {
        el.removeAttribute('open');
      }
    }
  }, [abierto]);

  if (!abierto) return null;

  return (
    <dialog
      ref={ref}
      aria-labelledby="lightbox-titulo"
      onCancel={(e) => {
        e.preventDefault();
        onCerrar();
      }}
      onClick={(e) => {
        if (e.target === ref.current) onCerrar();
      }}
      className="m-auto w-[min(94vw,36rem)] rounded-tarjeta border-0 bg-tarjeta p-5 text-tinta-900"
    >
      <div className="flex flex-col gap-4">
        <header className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <h2 id="lightbox-titulo" className="m-0 text-titulo">
              {titulo}
            </h2>
            {subtitulo && <p className="m-0 text-etiqueta text-tinta-600">{subtitulo}</p>}
          </div>
          <button
            type="button"
            onClick={onCerrar}
            aria-label="Cerrar vista de foto"
            className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-superficie text-tinta-900 hover:bg-lavanda-100 hover:text-indigo-700"
          >
            <X className="size-5" aria-hidden="true" />
          </button>
        </header>

        <div className="overflow-hidden rounded-tarjeta-interna bg-superficie">
          <img src={src} alt={alt} className="max-h-[60dvh] w-full object-contain" />
        </div>

        {acciones && <div className="flex flex-wrap justify-end gap-2">{acciones}</div>}
      </div>
    </dialog>
  );
}
