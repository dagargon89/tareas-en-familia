import type { ButtonHTMLAttributes, ReactNode } from 'react';

type Variante = 'primario' | 'secundario' | 'suave';
type Tamano = 'normal' | 'compacto';

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: Variante;
  tamano?: Tamano;
  anchoCompleto?: boolean;
  icono?: ReactNode;
}

const VARIANTES: Record<Variante, string> = {
  primario: 'bg-indigo-600 text-blanco hover:bg-indigo-hover',
  secundario: 'bg-lavanda-100 text-indigo-700 hover:brightness-95',
  suave: 'bg-transparent text-indigo-700 hover:bg-lavanda-100',
};

const TAMANOS: Record<Tamano, string> = {
  normal: 'px-6 text-titulo',
  compacto: 'px-4 text-etiqueta',
};

/** Botón píldora con alto mínimo del nivel (RNF-03). */
export function Boton({
  variante = 'primario',
  tamano = 'normal',
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
      className={`inline-flex min-h-tactil items-center justify-center gap-2 rounded-boton transition duration-150 ease-rebote active:scale-95 motion-reduce:active:scale-100 disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100 ${TAMANOS[tamano]} ${VARIANTES[variante]} ${anchoCompleto ? 'w-full' : ''} ${className}`}
      {...resto}
    >
      {icono}
      {children}
    </button>
  );
}
