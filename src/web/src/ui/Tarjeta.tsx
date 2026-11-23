import type { HTMLAttributes } from 'react';

type Tono = 'superficie' | 'blanco' | 'lavanda' | 'ambar' | 'menta' | 'rosa';

const TONOS: Record<Tono, string> = {
  superficie: 'bg-superficie',
  blanco: 'bg-blanco',
  lavanda: 'bg-lavanda-100 text-indigo-700',
  ambar: 'bg-ambar-200 text-tinta-900',
  menta: 'bg-menta-200 text-tinta-900',
  rosa: 'bg-rosa-200 text-tinta-900',
};

interface Props extends HTMLAttributes<HTMLElement> {
  tono?: Tono;
  interna?: boolean;
  como?: 'article' | 'section' | 'div' | 'li';
}

/** Contenedor de radio generoso y sin sombra (doc 08 §2.3). */
export function Tarjeta({ tono = 'superficie', interna = false, como = 'div', className = '', ...resto }: Props) {
  const Etiqueta = como;
  return (
    <Etiqueta
      className={`${TONOS[tono]} ${interna ? 'rounded-tarjeta-interna p-4' : 'rounded-tarjeta p-6'} ${className}`}
      {...resto}
    />
  );
}
