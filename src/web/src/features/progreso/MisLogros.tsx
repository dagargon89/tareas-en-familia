import type { NivelUx, PerfilPublico, Progreso } from '../../api/ApiClient.ts';
import { useProgreso } from '../../hooks/useProgreso.ts';
import { usePerfiles } from '../../hooks/useSesion.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { Cargando } from '../../ui/Cargando.tsx';
import { BadgeGrid } from './BadgeGrid.tsx';
import { FamilyGoalBar } from './FamilyGoalBar.tsx';
import { StatTile } from './StatTile.tsx';
import { StreakBadge } from './StreakBadge.tsx';
import { XpWaveBar } from './XpWaveBar.tsx';

interface VistaProps {
  progreso: Progreso;
  nivel: NivelUx;
  perfiles: PerfilPublico[];
}

/** "Mis logros" (RF-08, RF-10, RF-11, RF-12). Solo su propio progreso: nunca rankings (RN-02). */
export function MisLogrosVista({ progreso: p, nivel, perfiles }: VistaProps) {
  const v = VOCABULARIO[nivel];
  const obtenidas = p.insignias.filter((i) => i.obtenida_en !== null).length;
  return (
    <section className="flex flex-col gap-6">
      <h1 className="m-0 text-display">{nivel === 'exploradoras' ? 'Mis insignias' : 'Mis logros'}</h1>

      <div className="flex flex-col gap-5 rounded-tarjeta bg-superficie p-6">
        <XpWaveBar xp={p.xp} nivel={p.nivel} siguiente={p.siguiente_nivel} nivelUx={nivel} />
        <div className="grid grid-cols-2 gap-3">
          <StatTile etiqueta={`${v.puntos} para canjear`} valor={p.disponible} tono="ambar" />
          <StatTile etiqueta="Esta semana" valor={`${Math.round(p.semana_actual.porcentaje)}%`} tono="menta" />
        </div>
        {nivel !== 'exploradoras' && p.disponible !== p.saldo && (
          <p className="m-0 text-etiqueta text-tinta-600">
            Tienes {p.saldo - p.disponible} {v.unidadPuntos} apartados en premios que pediste.
          </p>
        )}
        <div className="flex flex-wrap items-center gap-3">
          <StreakBadge semanas={p.racha_semanas} nivel={nivel} />
          <span className="text-etiqueta text-tinta-600">
            {p.semana_actual.aprobadas} de {p.semana_actual.asignadas} esta semana
          </span>
        </div>
      </div>

      {p.insignias.length > 0 && (
        <div className="flex flex-col gap-3">
          <h2 className="m-0 text-titulo">
            Insignias <span className="text-etiqueta text-tinta-600">({obtenidas} de {p.insignias.length})</span>
          </h2>
          <BadgeGrid insignias={p.insignias} />
        </div>
      )}

      {p.meta_familiar && (
        <div className="flex flex-col gap-3">
          <h2 className="m-0 text-titulo">Meta de la familia</h2>
          <FamilyGoalBar meta={p.meta_familiar} perfiles={perfiles} />
        </div>
      )}
    </section>
  );
}

export function MisLogros() {
  const progreso = useProgreso();
  const perfiles = usePerfiles();
  const nivel = useSesionStore((s) => s.nivel);
  if (progreso.isPending) return <Cargando />;
  if (progreso.isError) return <p className="rounded-tarjeta bg-lavanda-100 p-6 text-cuerpo text-indigo-700">No pudimos cargar tus logros. Intenta de nuevo.</p>;
  return <MisLogrosVista progreso={progreso.data} nivel={nivel} perfiles={perfiles.data ?? []} />;
}
