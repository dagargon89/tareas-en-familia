import type { ButtonHTMLAttributes, ReactNode } from 'react';

type Variante = 'primario' | 'secundario' | 'suave';

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: Variante;
  anchoCompleto?: boolean;
  icono?: ReactNode;
}

const VARIANTES: Record<Variante, string> = {
  primario: 'bg-indigo-600 text-blanco hover:bg-indigo-700',
  secundario: 'bg-lavanda-100 text-indigo-700',
  suave: 'bg-transparent text-indigo-700 hover:bg-lavanda-100',
};

/** Botón píldora con alto mínimo del nivel (RNF-03). */
export function Boton({
  variante = 'primario',
  anchoCompleto = false,
  icono,
  className = '',
  children,
  type = 'button',
  ...resto
}: Props) {
  return (
    <button
      type={type}
      className={`inline-flex min-h-tactil items-center justify-center gap-2 rounded-boton px-6 text-titulo transition duration-150 ease-rebote active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 ${VARIANTES[variante]} ${anchoCompleto ? 'w-full' : ''} ${className}`}
      {...resto}
    >
      {icono}
      {children}
    </button>
  );
}
