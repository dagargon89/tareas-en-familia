import { useEffect, useRef, useState } from 'react';
import { CATEGORIAS_EMOJI } from '../../lib/emojis.ts';

interface SelectorEmojiProps {
  abierto: boolean;
  onElegir: (emoji: string) => void;
  onCerrar: () => void;
}

/**
 * Selector local de emojis familiares (ADR-011). Sin recursos de terceros,
 * accesible por teclado y con botones amplios para todos los niveles UX.
 */
export function SelectorEmoji({ abierto, onElegir, onCerrar }: SelectorEmojiProps) {
  const [categoriaId, setCategoriaId] = useState(CATEGORIAS_EMOJI[0].id);
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!abierto) return;
    function alTecla(e: KeyboardEvent) {
      if (e.key === 'Escape') onCerrar();
    }
    document.addEventListener('keydown', alTecla);
    return () => document.removeEventListener('keydown', alTecla);
  }, [abierto, onCerrar]);

  if (!abierto) return null;

  const categoria = CATEGORIAS_EMOJI.find((c) => c.id === categoriaId) ?? CATEGORIAS_EMOJI[0];

  return (
    <div
      ref={panelRef}
      role="dialog"
      aria-label="Elegir emoji"
      className="mb-2 rounded-tarjeta border border-superficie bg-tarjeta p-3"
    >
      <div className="mb-2 flex items-center gap-1 overflow-x-auto pb-1" role="tablist" aria-label="Categorías de emojis">
        {CATEGORIAS_EMOJI.map((cat) => {
          const activa = cat.id === categoria.id;
          return (
            <button
              key={cat.id}
              type="button"
              role="tab"
              aria-selected={activa}
              onClick={() => setCategoriaId(cat.id)}
              className={`inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-boton px-3 text-etiqueta transition-colors ${
                activa ? 'bg-lavanda-100 text-indigo-700' : 'text-tinta-600 hover:bg-superficie'
              }`}
            >
              <span aria-hidden="true">{cat.icono}</span>
              <span>{cat.nombre}</span>
            </button>
          );
        })}
      </div>
      <div className="grid grid-cols-5 gap-1 sm:grid-cols-10">
        {categoria.emojis.map((emoji) => (
          <button
            key={emoji}
            type="button"
            aria-label={`Insertar ${emoji}`}
            onClick={() => onElegir(emoji)}
            className="flex min-h-11 items-center justify-center rounded-icono text-[24px] transition-colors hover:bg-lavanda-100 active:scale-95"
          >
            {emoji}
          </button>
        ))}
      </div>
    </div>
  );
}
