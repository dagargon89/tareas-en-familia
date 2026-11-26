import { RotateCw } from 'lucide-react';
import { Boton } from './Boton.tsx';

interface Props {
  mensaje: string;
  onReintentar?: () => void;
}

/** Mensaje amable de error con botón opcional para reintentar sin recargar toda la página. */
export function EstadoError({ mensaje, onReintentar }: Props) {
  return (
    <div role="alert" className="flex flex-col items-start gap-4 rounded-tarjeta bg-lavanda-100 p-6 text-cuerpo text-indigo-700">
      <p className="m-0">{mensaje}</p>
      {onReintentar && (
        <Boton variante="primario" tamano="compacto" icono={<RotateCw className="size-4" aria-hidden="true" />} onClick={onReintentar}>
          Reintentar
        </Boton>
      )}
    </div>
  );
}
