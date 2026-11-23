import type { NivelUx, RecompensaParaPerfil } from '../../api/ApiClient.ts';
import { iconoDe } from '../../lib/iconos.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { Boton } from '../../ui/Boton.tsx';

interface Props {
  recompensa: RecompensaParaPerfil;
  nivel: NivelUx;
  solicitada?: boolean;
  onCanjear?: (r: RecompensaParaPerfil) => void;
}

/** Tarjeta de premio: lavanda "Ya te alcanza" o cuánto falta (RF-09, CP-064). */
export function RewardCard({ recompensa: r, nivel, solicitada = false, onCanjear }: Props) {
  const v = VOCABULARIO[nivel];
  const Icono = iconoDe(r.icono);
  return (
    <article className={`flex flex-col gap-4 rounded-tarjeta p-6 ${r.alcanza ? 'bg-lavanda-100' : 'bg-superficie'}`}>
      <div className="flex items-center gap-4">
        <span className="flex size-14 shrink-0 items-center justify-center rounded-icono bg-tarjeta text-indigo-700">
          <Icono className="size-7" aria-hidden="true" />
        </span>
        <div className="flex flex-col">
          <h3 className="m-0 text-titulo">{r.titulo}</h3>
          <span className="text-etiqueta text-tinta-600">
            {r.costo} {v.unidadPuntos}
          </span>
        </div>
      </div>
      {solicitada ? (
        <p className="m-0 text-cuerpo text-indigo-700">Pedido. Papá lo está revisando</p>
      ) : r.alcanza ? (
        <div className="flex flex-col gap-2">
          <span className="text-cuerpo text-indigo-700">{v.yaTeAlcanza}</span>
          {onCanjear && (
            <Boton anchoCompleto onClick={() => onCanjear(r)}>
              Canjear
            </Boton>
          )}
        </div>
      ) : (
        <p className="m-0 text-cuerpo text-tinta-600">{v.teFaltan(r.faltan)}</p>
      )}
    </article>
  );
}
