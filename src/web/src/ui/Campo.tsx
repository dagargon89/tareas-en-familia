import { useId, type InputHTMLAttributes } from 'react';

interface Props extends InputHTMLAttributes<HTMLInputElement> {
  etiqueta: string;
  ayuda?: string | null;
  error?: string | null;
}

/** Campo de texto con etiqueta visible; los avisos van en índigo, nunca en rojo. */
export function Campo({ etiqueta, ayuda, error, className = '', ...resto }: Props) {
  const id = useId();
  const idAyuda = `${id}-ayuda`;
  const texto = error ?? ayuda;
  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={id} className="text-etiqueta">
        {etiqueta}
      </label>
      <input
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={texto ? idAyuda : undefined}
        className={`min-h-tactil rounded-tarjeta-interna border-2 bg-tarjeta px-4 text-cuerpo text-tinta-900 outline-none transition-[border-color,box-shadow] focus:border-indigo-600 focus:ring-3 focus:ring-indigo-600/25 ${error ? 'border-indigo-600' : 'border-transparent'} ${className}`}
        {...resto}
      />
      {texto && (
        <span id={idAyuda} className={`text-etiqueta ${error ? 'text-indigo-700' : 'text-tinta-600'}`}>
          {texto}
        </span>
      )}
    </div>
  );
}
