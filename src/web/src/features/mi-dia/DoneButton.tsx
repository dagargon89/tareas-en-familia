import { Camera, Check, LoaderCircle } from 'lucide-react';

interface Props {
  etiqueta: string;
  requiereFoto: boolean;
  cargando?: boolean;
  onListo: (origen: { x: number; y: number }) => void;
}

/** Píldora índigo; ancho completo en Exploradoras y Aventureras, compacta en Pro (doc 08 §4). */
export function DoneButton({ etiqueta, requiereFoto, cargando = false, onListo }: Props) {
  const Icono = cargando ? LoaderCircle : requiereFoto ? Camera : Check;
  return (
    <button
      type="button"
      disabled={cargando}
      aria-busy={cargando}
      onClick={(e) => {
        const r = e.currentTarget.getBoundingClientRect();
        onListo({ x: (r.left + r.width / 2) / window.innerWidth, y: (r.top + r.height / 2) / window.innerHeight });
      }}
      className="inline-flex min-h-tactil w-full items-center justify-center gap-2 rounded-boton bg-indigo-600 px-6 text-titulo text-blanco transition duration-150 ease-rebote hover:bg-indigo-hover active:scale-95 motion-reduce:active:scale-100 disabled:opacity-70 disabled:active:scale-100 [[data-nivel=pro]_&]:w-auto"
    >
      <Icono className={`size-[1.1em] ${cargando ? 'animate-spin motion-reduce:animate-none' : ''}`} aria-hidden="true" />
      {requiereFoto && !cargando ? `${etiqueta} · foto` : etiqueta}
    </button>
  );
}
