import { Check, ChevronLeft, ChevronRight, Hourglass, MessageCircleHeart } from 'lucide-react';
import type { EstadoInstancia, Instancia, PerfilPublico, Semana } from '../../api/ApiClient.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { diaCorto, fechaCorta } from '../../lib/fechas.ts';
import { iconoDe } from '../../lib/iconos.ts';

const MARCA: Record<EstadoInstancia, { Icono: typeof Check | null; texto: string }> = {
  pendiente: { Icono: null, texto: 'pendiente' },
  hecha: { Icono: Hourglass, texto: 'esperando aprobación' },
  aprobada: { Icono: Check, texto: 'aprobada' },
  devuelta: { Icono: MessageCircleHeart, texto: 'devuelta' },
};

interface Props {
  semana: Semana;
  hoy: string;
  /** En modo padre, para mostrar de quién es cada tarea. */
  perfiles?: PerfilPublico[];
  onSemana: (desplazamiento: -1 | 1) => void;
  puedeAtras?: boolean;
  puedeAdelante?: boolean;
}

function Chip({ i, perfil }: { i: Instancia; perfil?: PerfilPublico }) {
  const I = iconoDe(i.icono);
  const marca = MARCA[i.estado];
  const Avatar = perfil ? avatarDe(perfil.avatar) : null;
  return (
    <li
      className={`flex items-center gap-2 rounded-tarjeta-interna p-2 text-etiqueta ${i.estado === 'aprobada' ? 'bg-menta-200' : 'bg-blanco'}`}
      aria-label={`${i.titulo}${i.hora ? ` a las ${i.hora}` : ''}${perfil ? `, ${perfil.apodo}` : ''}, ${marca.texto}`}
    >
      {Avatar && perfil && (
        <span className={`flex size-6 shrink-0 items-center justify-center rounded-full ${FONDO_COLOR[perfil.color]}`} aria-hidden="true">
          <Avatar className="size-3.5" />
        </span>
      )}
      <span className={`flex size-7 shrink-0 items-center justify-center rounded-icono ${FONDO_COLOR[i.color]}`} aria-hidden="true">
        <I className="size-4" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block truncate">{i.titulo}</span>
        {(i.hora || i.fecha_entrega) && (
          <span className="block text-tinta-600">
            {i.hora}
            {i.hora && i.fecha_entrega ? ' · ' : ''}
            {i.fecha_entrega ? `entrega ${fechaCorta(i.fecha_entrega)}` : ''}
          </span>
        )}
      </span>
      {marca.Icono && <marca.Icono className="size-4 shrink-0 text-indigo-700" aria-hidden="true" />}
    </li>
  );
}

/**
 * Vista semanal (RF-14, doc 08 §4): siete columnas en tablet y lista por día en celular.
 * Las actividades muestran su hora; en modo padre cada tarea lleva el avatar de la hija.
 */
export function WeekCalendar({ semana, hoy, perfiles, onSemana, puedeAtras = true, puedeAdelante = true }: Props) {
  const perfil = (id: number) => perfiles?.find((p) => p.id === id);
  const flecha = 'inline-flex size-11 items-center justify-center rounded-full bg-superficie text-indigo-700 disabled:opacity-30';
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-3">
        <button type="button" className={flecha} aria-label="Semana anterior" disabled={!puedeAtras} onClick={() => onSemana(-1)}>
          <ChevronLeft className="size-5" aria-hidden="true" />
        </button>
        <span className="text-titulo">
          {fechaCorta(semana.desde)} – {fechaCorta(semana.hasta)}
        </span>
        <button type="button" className={flecha} aria-label="Semana siguiente" disabled={!puedeAdelante} onClick={() => onSemana(1)}>
          <ChevronRight className="size-5" aria-hidden="true" />
        </button>
      </div>
      <ol className="m-0 grid list-none grid-cols-1 gap-3 p-0 md:grid-cols-7 md:gap-2">
        {semana.dias.map((d) => {
          const { semana: nombre, numero } = diaCorto(d.fecha);
          const esHoy = d.fecha === hoy;
          return (
            <li key={d.fecha} className={`flex flex-col gap-2 rounded-tarjeta p-3 md:min-h-40 md:p-2 ${esHoy ? 'bg-lavanda-100' : 'bg-superficie'}`} aria-current={esHoy ? 'date' : undefined}>
              <span className={`flex items-baseline gap-2 text-etiqueta ${esHoy ? 'text-indigo-700' : 'text-tinta-600'}`}>
                <span className="capitalize">{nombre}</span>
                <span className="text-titulo">{numero}</span>
                {esHoy && <span className="md:sr-only">· hoy</span>}
              </span>
              {d.instancias.length === 0 ? (
                <span className="text-etiqueta text-tinta-600">Libre</span>
              ) : (
                <ul className="m-0 flex list-none flex-col gap-1.5 p-0">
                  {d.instancias.map((i) => (
                    <Chip key={i.id} i={i} perfil={perfil(i.perfil_id)} />
                  ))}
                </ul>
              )}
            </li>
          );
        })}
      </ol>
    </div>
  );
}
