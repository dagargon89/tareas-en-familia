import type { Nivel, NivelUx } from '../../api/ApiClient.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';

interface Props {
  xp: number;
  nivel: Nivel;
  siguiente: Nivel | null;
  nivelUx: NivelUx;
}

/** Barra de progreso con trazo ondulado índigo (RF-08, doc 08 §4). */
export function XpWaveBar({ xp, nivel, siguiente, nivelUx }: Props) {
  const v = VOCABULARIO[nivelUx];
  const tramo = siguiente ? siguiente.xp_desde - nivel.xp_desde : 1;
  const avance = siguiente ? Math.min(1, Math.max(0, (xp - nivel.xp_desde) / tramo)) : 1;
  const faltan = siguiente ? Math.max(0, siguiente.xp_desde - xp) : 0;
  const porcentaje = Math.round(avance * 100);

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-baseline justify-between gap-4">
        <span className="text-titulo">{nivel.nombre}</span>
        <span className="text-etiqueta text-tinta-600">
          {xp} {v.unidadPuntos}
        </span>
      </div>
      <div
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={porcentaje}
        aria-label={`Progreso hacia ${siguiente?.nombre ?? 'el nivel máximo'}`}
        className="relative h-6 overflow-hidden rounded-boton bg-lavanda-100"
      >
        <div
          className="h-full rounded-boton bg-indigo-600 transition-[width] duration-700 ease-rebote"
          style={{ width: `${porcentaje}%` }}
        >
          <svg className="h-full w-full" preserveAspectRatio="none" viewBox="0 0 120 24" aria-hidden="true">
            <path
              d="M0 12 Q 7.5 4 15 12 T 30 12 T 45 12 T 60 12 T 75 12 T 90 12 T 105 12 T 120 12"
              fill="none"
              stroke="var(--tf-lavanda-100)"
              strokeOpacity="0.55"
              strokeWidth="2.5"
            />
          </svg>
        </div>
      </div>
      <span className="text-cuerpo text-tinta-600">
        {siguiente ? v.faltanPara(faltan, siguiente.nombre) : '¡Llegaste al nivel más alto!'}
      </span>
    </div>
  );
}
