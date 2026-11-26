import { ChevronDown, ChevronUp, Search } from 'lucide-react';
import { useState } from 'react';
import { FONDO_COLOR } from '../lib/avatares.ts';
import { ICONOS, iconoDe } from '../lib/iconos.ts';

interface Props {
  valor: string;
  onCambiar: (clave: string) => void;
  /** Claves sugeridas que se muestran siempre arriba antes de expandir los 52 íconos. */
  sugeridos?: readonly string[];
  fondoInactivo?: 'superficie' | 'tarjeta';
}

const SUGERIDOS_BASE = ['brillo', 'cama', 'libro', 'lapiz', 'mochila', 'comida', 'dientes', 'perro', 'musica', 'bici', 'regalo', 'helado'] as const;

/** Selector de íconos compacto con sugeridos, búsqueda por nombre y expansión de todo el catálogo. */
export function SelectorIcono({ valor, onCambiar, sugeridos = SUGERIDOS_BASE, fondoInactivo = 'superficie' }: Props) {
  const [expandido, setExpandido] = useState(false);
  const [busqueda, setBusqueda] = useState('');
  const todas = Object.entries(ICONOS);
  const filtro = busqueda.trim().toLowerCase();

  const lista = filtro
    ? todas.filter(([clave]) => clave.includes(filtro))
    : expandido
      ? todas
      : todas.filter(([clave]) => clave === valor || sugeridos.includes(clave));

  const Actual = iconoDe(valor);
  const claseInactivo = fondoInactivo === 'tarjeta' ? 'bg-tarjeta text-tinta-900 hover:bg-lavanda-100' : 'bg-superficie text-tinta-900 hover:bg-lavanda-100';

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <span className={`flex size-10 shrink-0 items-center justify-center rounded-icono ${FONDO_COLOR.indigo}`} aria-hidden="true">
          <Actual className="size-5" />
        </span>
        <div className="relative min-w-44 flex-1">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tinta-600" aria-hidden="true" />
          <input
            type="search"
            value={busqueda}
            onChange={(e) => {
              setBusqueda(e.target.value);
              if (e.target.value.trim()) setExpandido(true);
            }}
            placeholder="Buscar ícono (ej. libro, bici, helado)…"
            aria-label="Buscar ícono"
            className="min-h-11 w-full rounded-boton border-2 border-transparent bg-tarjeta py-2 pr-4 pl-9 text-etiqueta text-tinta-900 outline-none focus:border-indigo-600 focus:ring-3 focus:ring-indigo-600/25"
          />
        </div>
        {!filtro && (
          <button
            type="button"
            onClick={() => setExpandido((v) => !v)}
            className="inline-flex min-h-11 items-center gap-1 rounded-boton bg-lavanda-100 px-4 text-etiqueta text-indigo-700 hover:brightness-95"
          >
            {expandido ? (
              <>
                Ver menos <ChevronUp className="size-4" aria-hidden="true" />
              </>
            ) : (
              <>
                Ver todos ({todas.length}) <ChevronDown className="size-4" aria-hidden="true" />
              </>
            )}
          </button>
        )}
      </div>

      {lista.length === 0 ? (
        <p className="m-0 text-etiqueta text-tinta-600">No encontramos un ícono con «{busqueda}».</p>
      ) : (
        <div className="grid grid-cols-6 gap-2 sm:grid-cols-10">
          {lista.map(([clave, Icono]) => (
            <button
              key={clave}
              type="button"
              aria-label={clave}
              aria-pressed={valor === clave}
              title={clave}
              onClick={() => onCambiar(clave)}
              className={`flex aspect-square items-center justify-center rounded-icono transition-colors ${valor === clave ? FONDO_COLOR.indigo : claseInactivo}`}
            >
              <Icono className="size-1/2" aria-hidden="true" />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
