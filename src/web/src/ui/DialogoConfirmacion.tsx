import { useEffect, useRef } from 'react';
import { Boton } from './Boton.tsx';

interface Props {
  abierto: boolean;
  titulo: string;
  mensaje: string;
  textoConfirmar?: string;
  textoCancelar?: string;
  ocupado?: boolean;
  onConfirmar: () => void;
  onCancelar: () => void;
}

/**
 * Diálogo modal accesible del design system "Formas vivas" (reemplaza window.confirm).
 * Usa <dialog> nativo con respaldo para entornos de prueba sin showModal.
 */
export function DialogoConfirmacion({
  abierto,
  titulo,
  mensaje,
  textoConfirmar = 'Confirmar',
  textoCancelar = 'Cancelar',
  ocupado = false,
  onConfirmar,
  onCancelar,
}: Props) {
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
      aria-labelledby="dialogo-titulo"
      aria-describedby="dialogo-mensaje"
      onCancel={(e) => {
        e.preventDefault();
        if (!ocupado) onCancelar();
      }}
      onClick={(e) => {
        if (e.target === ref.current && !ocupado) onCancelar();
      }}
      className="m-auto w-[min(92vw,28rem)] rounded-tarjeta border-0 bg-tarjeta p-6 text-tinta-900"
    >
      <div className="flex flex-col gap-4">
        <h2 id="dialogo-titulo" className="m-0 text-titulo">
          {titulo}
        </h2>
        <p id="dialogo-mensaje" className="m-0 whitespace-pre-line text-cuerpo text-tinta-600">
          {mensaje}
        </p>
        <div className="mt-2 flex flex-wrap justify-end gap-2">
          <Boton variante="suave" tamano="compacto" disabled={ocupado} onClick={onCancelar}>
            {textoCancelar}
          </Boton>
          <Boton variante="primario" tamano="compacto" disabled={ocupado} onClick={onConfirmar}>
            {ocupado ? 'Procesando…' : textoConfirmar}
          </Boton>
        </div>
      </div>
    </dialog>
  );
}
