import { Check, ChevronLeft, ChevronRight, Hourglass, MessageCircleHeart, Sun } from 'lucide-react';
import { useState } from 'react';
import type { Dia, EstadoInstancia, Instancia, PerfilPublico, Semana } from '../../api/ApiClient.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { diaCorto, fechaCorta, fechaLarga } from '../../lib/fechas.ts';
import { iconoDe } from '../../lib/iconos.ts';
import { ESCRITORIO, useMediaQuery } from '../../lib/useMediaQuery.ts';

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

function etiqueta(i: Instancia, perfil?: PerfilPublico): string {
  return `${i.titulo}${i.hora ? ` a las ${i.hora}` : ''}${perfil ? `, ${perfil.apodo}` : ''}, ${MARCA[i.estado].texto}`;
}

/** Tareas del día ya hechas por la hija (esperando o aprobadas). */
const listasDe = (d: Dia) => d.instancias.filter((i) => i.estado === 'hecha' || i.estado === 'aprobada').length;

const fondoEstado = (e: EstadoInstancia) => (e === 'aprobada' ? 'bg-menta-200' : 'bg-blanco');

function AvatarPerfil({ perfil, tamano = 'size-8' }: { perfil: PerfilPublico; tamano?: string }) {
  const Avatar = avatarDe(perfil.avatar);
  return (
    <span className={`flex ${tamano} shrink-0 items-center justify-center rounded-full ${FONDO_COLOR[perfil.color]}`} aria-hidden="true">
      <Avatar className="size-1/2" />
    </span>
  );
}

function IconoTarea({ i, tamano }: { i: Instancia; tamano: string }) {
  const I = iconoDe(i.icono);
  return (
    <span className={`flex ${tamano} shrink-0 items-center justify-center rounded-icono ${FONDO_COLOR[i.color]}`} aria-hidden="true">
      <I className="size-1/2" />
    </span>
  );
}

function MarcaEstado({ estado }: { estado: EstadoInstancia }) {
  const { Icono } = MARCA[estado];
  return Icono ? <Icono className="size-4 shrink-0 text-indigo-700" aria-hidden="true" /> : null;
}

/** Hora y entrega en una línea ("18:00 · entrega 26 nov"). */
function Detalle({ i }: { i: Instancia }) {
  if (!i.hora && !i.fecha_entrega) return null;
  return (
    <span className="block text-etiqueta text-tinta-600">
      {i.hora}
      {i.hora && i.fecha_entrega ? ' · ' : ''}
      {i.fecha_entrega ? `entrega ${fechaCorta(i.fecha_entrega)}` : ''}
    </span>
  );
}

/**
 * Hijas que aparecen en la semana, en el orden de los perfiles (nunca por desempeño, RN-02).
 * Sin perfiles (vista de la hija) hay un solo carril sin encabezado.
 */
function carriles(semana: Semana, perfiles?: PerfilPublico[]): (PerfilPublico | null)[] {
  if (!perfiles) return [null];
  const presentes = new Set(semana.dias.flatMap((d) => d.instancias.map((i) => i.perfil_id)));
  const conTareas = perfiles.filter((p) => presentes.has(p.id));
  return conTareas.length > 0 ? conTareas : [null];
}

// ===== Escritorio: tablero con una fila por hija y una columna por día =====

function TarjetaTablero({ i, perfil }: { i: Instancia; perfil?: PerfilPublico }) {
  return (
    <li className={`flex flex-col gap-1.5 rounded-tarjeta-interna p-2 shadow-[0_1px_2px_rgb(22_22_26/0.06)] ${fondoEstado(i.estado)}`} aria-label={etiqueta(i, perfil)} title={i.titulo}>
      <span className="flex items-center gap-2">
        <IconoTarea i={i} tamano="size-7" />
        {i.hora && <span className="text-etiqueta text-tinta-600">{i.hora}</span>}
        <span className="ml-auto">
          <MarcaEstado estado={i.estado} />
        </span>
      </span>
      <span className="line-clamp-2 text-etiqueta break-words text-tinta-900">{i.titulo}</span>
      {i.fecha_entrega && <span className="text-etiqueta text-tinta-600">entrega {fechaCorta(i.fecha_entrega)}</span>}
    </li>
  );
}

function Tablero({ semana, hoy, perfiles }: Pick<Props, 'semana' | 'hoy' | 'perfiles'>) {
  const filas = carriles(semana, perfiles);
  const conEncabezado = filas.some((p) => p !== null);
  return (
    <table className="w-full table-fixed border-separate border-spacing-1.5">
      <caption className="sr-only">Tareas de la semana del {fechaCorta(semana.desde)} al {fechaCorta(semana.hasta)}</caption>
      <colgroup>
        {conEncabezado && <col className="w-32" />}
        {semana.dias.map((d) => (
          <col key={d.fecha} />
        ))}
      </colgroup>
      <thead>
        <tr>
          {conEncabezado && <td />}
          {semana.dias.map((d) => {
            const { semana: nombre, numero } = diaCorto(d.fecha);
            const esHoy = d.fecha === hoy;
            return (
              <th
                key={d.fecha}
                scope="col"
                aria-current={esHoy ? 'date' : undefined}
                className={`rounded-tarjeta-interna px-2 py-2 text-left font-normal ${esHoy ? 'bg-indigo-600 text-blanco' : 'text-tinta-600'}`}
              >
                <span className="block text-etiqueta">
                  <span className="inline-block first-letter:uppercase">{nombre}</span>
                  {esHoy && ' · hoy'}
                </span>
                <span className={`block text-titulo ${esHoy ? '' : 'text-tinta-900'}`}>{numero}</span>
              </th>
            );
          })}
        </tr>
      </thead>
      <tbody>
        {filas.map((perfil) => (
          <tr key={perfil?.id ?? 'todas'}>
            {conEncabezado && (
              <th scope="row" className="rounded-tarjeta bg-superficie p-3 text-left align-top font-normal">
                {perfil && (
                  <span className="flex flex-col items-start gap-2">
                    <AvatarPerfil perfil={perfil} tamano="size-10" />
                    <span className="text-etiqueta font-semibold text-tinta-900">{perfil.apodo}</span>
                  </span>
                )}
              </th>
            )}
            {semana.dias.map((d) => {
              const delCarril = perfil ? d.instancias.filter((i) => i.perfil_id === perfil.id) : d.instancias;
              const esHoy = d.fecha === hoy;
              return (
                <td key={d.fecha} className={`rounded-tarjeta p-1.5 align-top ${esHoy ? 'bg-lavanda-100' : 'bg-superficie'}`}>
                  {delCarril.length === 0 ? (
                    <span className="block px-1 py-2 text-etiqueta text-tinta-600">Libre</span>
                  ) : (
                    <ul className="m-0 flex list-none flex-col gap-1.5 p-0">
                      {delCarril.map((i) => (
                        <TarjetaTablero key={i.id} i={i} perfil={perfil ?? undefined} />
                      ))}
                    </ul>
                  )}
                </td>
              );
            })}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

// ===== Tablet y celular: tira de días y la agenda del día elegido =====

function FilaAgenda({ i, perfil }: { i: Instancia; perfil?: PerfilPublico }) {
  return (
    <li className={`flex items-center gap-3 rounded-tarjeta-interna p-3 ${fondoEstado(i.estado)}`} aria-label={etiqueta(i, perfil)}>
      <IconoTarea i={i} tamano="size-10" />
      <span className="min-w-0 flex-1">
        <span className="block text-cuerpo break-words text-tinta-900">{i.titulo}</span>
        <Detalle i={i} />
      </span>
      <MarcaEstado estado={i.estado} />
    </li>
  );
}

function TiraDias({ dias, hoy, elegido, onElegir }: { dias: Dia[]; hoy: string; elegido: string; onElegir: (fecha: string) => void }) {
  return (
    <div className="grid grid-cols-7 gap-1" role="group" aria-label="Días de la semana">
      {dias.map((d) => {
        const { semana: nombre, numero } = diaCorto(d.fecha);
        const activo = d.fecha === elegido;
        const esHoy = d.fecha === hoy;
        const total = d.instancias.length;
        return (
          <button
            key={d.fecha}
            type="button"
            aria-pressed={activo}
            aria-current={esHoy ? 'date' : undefined}
            onClick={() => onElegir(d.fecha)}
            className={`flex min-h-tactil min-w-0 flex-col items-center justify-center gap-1 rounded-tarjeta-interna px-0 py-2 transition-colors ${
              activo ? 'bg-indigo-600 text-blanco' : esHoy ? 'bg-lavanda-100 text-indigo-700' : 'bg-superficie text-tinta-900'
            }`}
          >
            <span className="text-etiqueta first-letter:uppercase">{nombre}</span>
            <span className="text-titulo">{numero}</span>
            {/* El nombre accesible empieza con lo visible ("lun 23", WCAG 2.5.3) y agrega el resto. */}
            <span className="sr-only">
              {esHoy ? ', hoy' : ''}, {total === 0 ? 'libre' : `${listasDe(d)} de ${total} listas`}
            </span>
            {/* Avance del día: tareas listas (hechas o aprobadas) sobre el total. */}
            <span className={`h-1 w-3/5 overflow-hidden rounded-full ${total === 0 ? 'opacity-0' : activo ? 'bg-blanco/30' : 'bg-indigo-600/15'}`} aria-hidden="true">
              <span className={`block h-full rounded-full ${activo ? 'bg-blanco' : 'bg-indigo-600'}`} style={{ width: `${total === 0 ? 0 : Math.round((listasDe(d) / total) * 100)}%` }} />
            </span>
          </button>
        );
      })}
    </div>
  );
}

function Agenda({ semana, hoy, perfiles }: Pick<Props, 'semana' | 'hoy' | 'perfiles'>) {
  const inicial = semana.dias.some((d) => d.fecha === hoy) ? hoy : semana.desde;
  const [elegido, setElegido] = useState(inicial);
  const dia = semana.dias.find((d) => d.fecha === elegido) ?? semana.dias[0];
  const grupos = carriles({ ...semana, dias: [dia] }, perfiles);
  const listas = listasDe(dia);

  return (
    <div className="flex flex-col gap-4">
      <TiraDias dias={semana.dias} hoy={hoy} elegido={dia.fecha} onElegir={setElegido} />
      <section className="flex flex-col gap-3 rounded-tarjeta bg-superficie p-3" aria-labelledby={`dia-${dia.fecha}`}>
        <header className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 px-1">
          <h2 id={`dia-${dia.fecha}`} className="m-0 text-titulo first-letter:uppercase">
            {fechaLarga(dia.fecha)}
          </h2>
          {dia.instancias.length > 0 && (
            <span className="text-etiqueta text-tinta-600">
              {listas} de {dia.instancias.length} listas
            </span>
          )}
        </header>
        {dia.instancias.length === 0 ? (
          <p className="m-0 flex items-center gap-2 rounded-tarjeta-interna bg-blanco p-4 text-cuerpo text-tinta-600">
            <Sun className="size-5 text-indigo-700" aria-hidden="true" /> Día libre
          </p>
        ) : (
          grupos.map((perfil) => {
            const suyas = perfil ? dia.instancias.filter((i) => i.perfil_id === perfil.id) : dia.instancias;
            return (
              <div key={perfil?.id ?? 'todas'} className="flex flex-col gap-2">
                {perfil && grupos.length > 1 && (
                  <h3 className="m-0 flex items-center gap-2 px-1 pt-1 text-etiqueta font-semibold text-tinta-900">
                    <AvatarPerfil perfil={perfil} tamano="size-7" />
                    {perfil.apodo}
                    <span className="font-normal text-tinta-600">· {suyas.length}</span>
                  </h3>
                )}
                <ul className="m-0 flex list-none flex-col gap-2 p-0">
                  {suyas.map((i) => (
                    <FilaAgenda key={i.id} i={i} perfil={perfil ?? undefined} />
                  ))}
                </ul>
              </div>
            );
          })
        )}
      </section>
    </div>
  );
}

/**
 * Vista semanal (RF-14, doc 08 §4). En escritorio, tablero con una fila por hija y una columna por día;
 * en tablet y celular, tira de días con la agenda del día elegido (hoy, si está en la semana).
 * En modo padre cada hija aparece una sola vez (carril o grupo) en el orden de los perfiles.
 */
export function WeekCalendar({ semana, hoy, perfiles, onSemana, puedeAtras = true, puedeAdelante = true }: Props) {
  const escritorio = useMediaQuery(ESCRITORIO);
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
      {escritorio ? (
        <Tablero semana={semana} hoy={hoy} perfiles={perfiles} />
      ) : (
        <Agenda key={semana.desde} semana={semana} hoy={hoy} perfiles={perfiles} />
      )}
    </div>
  );
}
