import { Lock } from 'lucide-react';
import type { Insignia } from '../../api/ApiClient.ts';
import { FONDO_COLOR } from '../../lib/avatares.ts';
import { iconoDe } from '../../lib/iconos.ts';

/** Rejilla de insignias: obtenidas a color, bloqueadas con borde punteado y candado (RF-11). */
export function BadgeGrid({ insignias }: { insignias: Insignia[] }) {
  return (
    <ul className="grid grid-cols-2 gap-4 sm:grid-cols-4">
      {insignias.map((ins) => {
        const obtenida = ins.obtenida_en !== null;
        const Icono = obtenida ? iconoDe(ins.icono) : Lock;
        return (
          <li key={ins.codigo} className="flex flex-col items-center gap-2 text-center">
            <span
              className={`flex size-20 items-center justify-center rounded-full ${
                obtenida ? FONDO_COLOR[ins.color] : 'border-2 border-dashed border-tinta-600 text-tinta-600'
              }`}
            >
              <Icono className="size-9" aria-hidden="true" />
            </span>
            <span className="text-etiqueta">{ins.nombre}</span>
            {!obtenida && <span className="text-etiqueta text-tinta-600">{ins.descripcion}</span>}
          </li>
        );
      })}
    </ul>
  );
}
