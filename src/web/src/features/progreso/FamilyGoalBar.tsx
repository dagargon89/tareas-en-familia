import { PartyPopper } from 'lucide-react';
import type { Meta, PerfilPublico } from '../../api/ApiClient.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';

/** Barra común de la meta familiar con los avatares encima (RF-12). Sin comparar hermanas (RN-02). */
export function FamilyGoalBar({ meta, perfiles }: { meta: Meta; perfiles: PerfilPublico[] }) {
  const avance = Math.min(1, meta.progreso_puntos / meta.objetivo_puntos);
  const cumplida = meta.estado === 'cumplida';
  return (
    <div className="flex flex-col gap-3 rounded-tarjeta bg-superficie p-6">
      <div className="flex items-start justify-between gap-4">
        <div className="flex flex-col">
          <span className="text-titulo">{meta.titulo}</span>
          <span className="text-etiqueta text-tinta-600">Premio: {meta.premio}</span>
        </div>
        <div className="flex -space-x-3">
          {perfiles.map((p) => {
            const A = avatarDe(p.avatar);
            return (
              <span key={p.id} title={p.apodo} className={`flex size-10 items-center justify-center rounded-full ring-4 ring-[var(--tf-superficie)] ${FONDO_COLOR[p.color]}`}>
                <A className="size-5" aria-hidden="true" />
              </span>
            );
          })}
        </div>
      </div>
      <div
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={meta.objetivo_puntos}
        aria-valuenow={meta.progreso_puntos}
        aria-label="Meta familiar"
        className="h-5 overflow-hidden rounded-boton bg-lavanda-100"
      >
        <div className="h-full rounded-boton bg-indigo-600 transition-[width] duration-700" style={{ width: `${avance * 100}%` }} />
      </div>
      <span className="flex items-center gap-2 text-cuerpo">
        {cumplida && <PartyPopper className="size-5" aria-hidden="true" />}
        {cumplida ? '¡Lo lograron juntas!' : `${meta.progreso_puntos} de ${meta.objetivo_puntos} entre todas`}
      </span>
    </div>
  );
}
