import { useQuery } from '@tanstack/react-query';
import { CalendarDays, Flame, Gift, Inbox, Wallet } from 'lucide-react';
import { Link } from 'react-router';
import type { Panel as PanelDatos, PanelHija, PerfilPublico } from '../../api/ApiClient.ts';
import { api } from '../../api/HttpApiClient.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { fechaCorta } from '../../lib/fechas.ts';
import { Cargando } from '../../ui/Cargando.tsx';
import { FamilyGoalBar } from '../progreso/FamilyGoalBar.tsx';

function TarjetaHija({ h }: { h: PanelHija }) {
  const Avatar = avatarDe(h.perfil.avatar);
  const pct = Math.round(h.porcentaje_semana);
  return (
    <article className="flex flex-col gap-4 rounded-tarjeta bg-superficie p-5">
      <header className="flex items-center gap-3">
        <span className={`flex size-12 items-center justify-center rounded-full ${FONDO_COLOR[h.perfil.color]}`}>
          <Avatar className="size-6" aria-hidden="true" />
        </span>
        <h2 className="m-0 text-titulo">{h.perfil.apodo}</h2>
      </header>

      <div className="flex flex-col gap-1">
        <div className="flex items-baseline justify-between text-etiqueta text-tinta-600">
          <span>Esta semana</span>
          <span className="text-titulo text-tinta-900">{pct}%</span>
        </div>
        <div role="progressbar" aria-label={`Semana de ${h.perfil.apodo}`} aria-valuemin={0} aria-valuemax={100} aria-valuenow={pct} className="h-3 overflow-hidden rounded-boton bg-lavanda-100">
          <div className="h-full rounded-boton bg-indigo-600" style={{ width: `${Math.min(100, pct)}%` }} />
        </div>
      </div>

      <dl className="m-0 grid grid-cols-3 gap-2 text-center">
        <div className="rounded-tarjeta-interna bg-tarjeta p-2">
          <dt className="flex items-center justify-center gap-1 text-etiqueta text-tinta-600">
            <Inbox className="size-3.5" aria-hidden="true" /> Por aprobar
          </dt>
          <dd className="m-0 text-titulo">{h.por_aprobar}</dd>
        </div>
        <div className="rounded-tarjeta-interna bg-tarjeta p-2">
          <dt className="flex items-center justify-center gap-1 text-etiqueta text-tinta-600">
            <Flame className="size-3.5" aria-hidden="true" /> Racha
          </dt>
          <dd className="m-0 text-titulo">{h.racha_semanas}</dd>
        </div>
        <div className="rounded-tarjeta-interna bg-tarjeta p-2">
          <dt className="flex items-center justify-center gap-1 text-etiqueta text-tinta-600">
            <Wallet className="size-3.5" aria-hidden="true" /> Puntos
          </dt>
          <dd className="m-0 text-titulo">{h.disponible}</dd>
        </div>
      </dl>

      {h.proximas_entregas.length > 0 && (
        <div className="flex flex-col gap-1">
          <span className="flex items-center gap-1 text-etiqueta text-tinta-600">
            <CalendarDays className="size-3.5" aria-hidden="true" /> Próximas entregas
          </span>
          <ul className="m-0 flex list-none flex-col gap-1 p-0">
            {h.proximas_entregas.map((i) => (
              <li key={i.id} className="flex justify-between gap-2 text-cuerpo">
                <span className="truncate">
                  {i.titulo}
                  {i.materia ? <span className="text-etiqueta text-tinta-600"> · {i.materia}</span> : null}
                </span>
                <span className="shrink-0 text-etiqueta text-tinta-600">{i.fecha_entrega ? fechaCorta(i.fecha_entrega) : ''}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </article>
  );
}

/**
 * Panel del padre (RF-16). Cada hija en su propia tarjeta, sin ordenarlas por desempeño
 * ni compararlas (RN-02): se muestran en el orden de sus perfiles.
 */
export function PanelVista({ panel, perfiles }: { panel: PanelDatos; perfiles: PerfilPublico[] }) {
  const porAprobar = panel.hijas.reduce((n, h) => n + h.por_aprobar, 0);
  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <h1 className="m-0 text-display">Panel</h1>
        <span className="text-etiqueta text-tinta-600">Semana {panel.semana_iso}</span>
      </div>

      {(porAprobar > 0 || panel.canjes_pendientes.length > 0) && (
        <div className="flex flex-wrap gap-2">
          {porAprobar > 0 && (
            <Link to="/padre/aprobaciones" className="inline-flex min-h-tactil items-center gap-2 rounded-boton bg-indigo-600 px-5 text-etiqueta text-blanco">
              <Inbox className="size-4" aria-hidden="true" /> {porAprobar} por aprobar
            </Link>
          )}
          {panel.canjes_pendientes.length > 0 && (
            <Link to="/padre/premios" className="inline-flex min-h-tactil items-center gap-2 rounded-boton bg-lavanda-100 px-5 text-etiqueta text-indigo-700">
              <Gift className="size-4" aria-hidden="true" /> {panel.canjes_pendientes.length} premios pedidos
            </Link>
          )}
        </div>
      )}

      {panel.hijas.length === 0 ? (
        <p className="m-0 rounded-tarjeta bg-superficie p-6 text-cuerpo text-tinta-600">
          Todavía no hay perfiles. <Link to="/padre/perfiles" className="text-indigo-700 underline">Crea el primero</Link>.
        </p>
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {panel.hijas.map((h) => (
            <TarjetaHija key={h.perfil.id} h={h} />
          ))}
        </div>
      )}

      {panel.meta_familiar ? (
        <FamilyGoalBar meta={panel.meta_familiar} perfiles={perfiles} />
      ) : (
        <Link to="/padre/metas" className="rounded-tarjeta bg-superficie p-5 text-cuerpo text-indigo-700 underline">
          Crear una meta familiar
        </Link>
      )}
    </section>
  );
}

export function Panel() {
  const panel = useQuery({ queryKey: ['admin', 'panel'], queryFn: () => api.panel(), refetchInterval: 60_000 });
  if (panel.isPending) return <Cargando />;
  if (panel.isError) return <p className="text-cuerpo text-indigo-700">No se pudo cargar el panel. Intenta de nuevo.</p>;
  return <PanelVista panel={panel.data} perfiles={panel.data.hijas.map((h) => h.perfil)} />;
}
