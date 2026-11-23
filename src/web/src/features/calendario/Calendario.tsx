import { useState } from 'react';
import { useAdminSemana, useSemana } from '../../hooks/useSemana.ts';
import { usePerfilesAdmin } from '../../hooks/usePerfilesAdmin.ts';
import { diasEntre, fechaLocal, lunesDe, sumarDias } from '../../lib/fechas.ts';
import { Cargando } from '../../ui/Cargando.tsx';
import { WeekCalendar } from './WeekCalendar.tsx';

/** Semanas hacia atrás y adelante. Adelante solo una: las instancias se generan a 7 días (RN-07). */
export const SEMANAS_ATRAS = 8;
export const SEMANAS_ADELANTE = 1;

function useNavegacionSemanas() {
  const hoy = fechaLocal();
  const actual = lunesDe(hoy);
  const [desde, setDesde] = useState(actual);
  const semanas = diasEntre(actual, desde) / 7;
  return {
    hoy,
    desde,
    puedeAtras: semanas > -SEMANAS_ATRAS,
    puedeAdelante: semanas < SEMANAS_ADELANTE,
    mover: (n: -1 | 1) => setDesde((d) => sumarDias(d, n * 7)),
  };
}

const ERROR = <p className="rounded-tarjeta bg-lavanda-100 p-6 text-cuerpo text-indigo-700">No pudimos cargar la semana. Intenta de nuevo.</p>;

/** Semana de la hija (RF-14, E-17). */
export function Calendario() {
  const nav = useNavegacionSemanas();
  const semana = useSemana(nav.desde);
  return (
    <section className="flex flex-col gap-4">
      <h1 className="m-0 text-display">Mi semana</h1>
      {semana.isPending ? <Cargando /> : semana.isError ? ERROR : (
        <WeekCalendar semana={semana.data} hoy={nav.hoy} onSemana={nav.mover} puedeAtras={nav.puedeAtras} puedeAdelante={nav.puedeAdelante} />
      )}
    </section>
  );
}

/** Semana en modo padre (RF-14, E-46): todas las hijas o una. */
export function SemanaPadre() {
  const nav = useNavegacionSemanas();
  const [perfilId, setPerfilId] = useState<number | undefined>(undefined);
  const perfiles = usePerfilesAdmin();
  const semana = useAdminSemana(nav.desde, perfilId);
  const activos = perfiles.data?.filter((p) => p.activo) ?? [];
  const filtro = (activo: boolean) => `min-h-tactil rounded-boton px-4 text-etiqueta ${activo ? 'bg-indigo-600 text-blanco' : 'bg-superficie'}`;
  return (
    <section className="flex flex-col gap-4">
      <h1 className="m-0 text-display">Semana</h1>
      <div className="flex flex-wrap gap-2">
        <button type="button" className={filtro(perfilId === undefined)} onClick={() => setPerfilId(undefined)}>
          Todas
        </button>
        {activos.map((p) => (
          <button key={p.id} type="button" className={filtro(perfilId === p.id)} onClick={() => setPerfilId(p.id)}>
            {p.apodo}
          </button>
        ))}
      </div>
      {semana.isPending ? <Cargando /> : semana.isError ? ERROR : (
        <WeekCalendar semana={semana.data} hoy={nav.hoy} perfiles={activos} onSemana={nav.mover} puedeAtras={nav.puedeAtras} puedeAdelante={nav.puedeAdelante} />
      )}
    </section>
  );
}
