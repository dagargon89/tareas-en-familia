import { useState, type ReactNode } from 'react';
import type { Instancia, NivelUx } from '../../api/ApiClient.ts';
import { fechaLarga, fechaLocal } from '../../lib/fechas.ts';
import { NIVELES, NOMBRE_NIVEL } from '../../niveles/nivel.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { FormasVivas } from '../../ui/FormasVivas.tsx';
import { AvatarSelector } from '../acceso/AvatarSelector.tsx';
import { PinPad } from '../acceso/PinPad.tsx';
import { celebrar } from '../mi-dia/Celebracion.ts';
import { TaskCard } from '../mi-dia/TaskCard.tsx';
import { ParentApprovalItem } from '../padre/ParentApprovalItem.tsx';
import { RewardCard } from '../premios/RewardCard.tsx';
import { BadgeGrid } from '../progreso/BadgeGrid.tsx';
import { FamilyGoalBar } from '../progreso/FamilyGoalBar.tsx';
import { StatTile } from '../progreso/StatTile.tsx';
import { StreakBadge } from '../progreso/StreakBadge.tsx';
import { XpWaveBar } from '../progreso/XpWaveBar.tsx';
import { BANDEJA, INSIGNIAS, INSTANCIAS, META, NIVEL_ACTUAL, NIVEL_SIGUIENTE, PERFILES, RECOMPENSAS } from './datosGaleria.ts';

function Seccion({ titulo, nota, children }: { titulo: string; nota?: string; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-4">
      <div>
        <h2 className="m-0 text-titulo">{titulo}</h2>
        {nota && <p className="m-0 text-etiqueta text-tinta-600">{nota}</p>}
      </div>
      {children}
    </section>
  );
}

/** Galería de componentes con datos fijos (solo desarrollo, ADR-008). */
export function Galeria() {
  const { nivel, setNivel } = useSesionStore();
  const [instancias, setInstancias] = useState<Instancia[]>(INSTANCIAS);
  const [fallos, setFallos] = useState(0);
  const [pinMsg, setPinMsg] = useState<string | null>(null);
  const [pedidas, setPedidas] = useState<number[]>([]);
  const v = VOCABULARIO[nivel];

  function marcar(i: Instancia, origen: { x: number; y: number }) {
    setInstancias((xs) => xs.map((x) => (x.id === i.id ? { ...x, estado: 'hecha', mensaje_devolucion: null } : x)));
    celebrar(nivel, origen);
  }

  return (
    <div className="relative min-h-dvh">
      <FormasVivas />
      <main className="mx-auto flex max-w-3xl flex-col gap-10 px-4 py-6 sm:px-8">
        <header className="sticky top-0 z-10 -mx-4 flex flex-col gap-3 bg-[color-mix(in_srgb,var(--tf-fondo)_88%,transparent)] px-4 py-3 backdrop-blur sm:-mx-8 sm:px-8">
          <div className="flex items-baseline justify-between gap-4">
            <h1 className="m-0 text-display">Galería</h1>
            <span className="text-etiqueta text-tinta-600">Solo desarrollo · datos fijos</span>
          </div>
          <div role="radiogroup" aria-label="Nivel de experiencia" className="flex flex-wrap gap-2">
            {NIVELES.map((n: NivelUx) => (
              <button
                key={n}
                type="button"
                role="radio"
                aria-checked={nivel === n}
                onClick={() => setNivel(n)}
                className={`min-h-11 rounded-boton px-5 text-etiqueta transition-colors ${nivel === n ? 'bg-indigo-600 text-blanco' : 'bg-lavanda-100 text-indigo-700'}`}
              >
                {NOMBRE_NIVEL[n]}
              </button>
            ))}
            <button type="button" onClick={() => setInstancias(INSTANCIAS)} className="min-h-11 rounded-boton px-5 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
              Reiniciar tareas
            </button>
          </div>
        </header>

        <Seccion titulo="Selector de perfil" nota="RF-01 · Vale está bloqueada 9 minutos">
          <AvatarSelector perfiles={PERFILES} onElegir={() => undefined} />
        </Seccion>

        <Seccion titulo="PIN" nota="Cualquier PIN distinto de 2580 cuenta como incorrecto">
          <PinPad
            titulo="Hola, Sofi. Escribe tu PIN"
            fallos={fallos}
            error={pinMsg}
            onCompleto={(pin) => {
              if (pin === '2580') {
                setPinMsg('¡Bienvenida!');
              } else {
                setFallos((f) => f + 1);
                setPinMsg(v.pinIncorrecto);
              }
            }}
          />
        </Seccion>

        <Seccion titulo={`Mi día · ${fechaLarga(fechaLocal())}`} nota="RF-04 y RF-05 · toca Listo para ver la celebración del nivel">
          <div className="flex flex-col gap-4">
            {instancias.map((i) => (
              <TaskCard key={i.id} instancia={i} nivel={nivel} puedeMarcar onListo={marcar} />
            ))}
          </div>
        </Seccion>

        <Seccion titulo="Mis logros" nota="RF-08, RF-10, RF-11 y RF-12">
          <div className="flex flex-col gap-6 rounded-tarjeta bg-superficie p-6">
            <XpWaveBar xp={612} nivel={NIVEL_ACTUAL} siguiente={NIVEL_SIGUIENTE} nivelUx={nivel} />
            <div className="grid grid-cols-2 gap-3">
              <StatTile etiqueta={`${v.puntos} para canjear`} valor={128} tono="ambar" />
              <StatTile etiqueta="Esta semana" valor="75%" tono="menta" />
            </div>
            <div className="flex flex-wrap gap-2">
              <StreakBadge semanas={3} nivel={nivel} />
              <StreakBadge semanas={0} nivel={nivel} />
            </div>
          </div>
          <BadgeGrid insignias={INSIGNIAS} />
          <FamilyGoalBar meta={META} perfiles={PERFILES} />
        </Seccion>

        <Seccion titulo="Premios" nota="RF-09 · CP-064">
          <div className="grid gap-4 sm:grid-cols-2">
            {RECOMPENSAS.map((r) => (
              <RewardCard key={r.id} recompensa={r} nivel={nivel} solicitada={pedidas.includes(r.id)} onCanjear={(x) => setPedidas((p) => [...p, x.id])} />
            ))}
          </div>
        </Seccion>

        <Seccion titulo="Bandeja del padre" nota="RF-07">
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {BANDEJA.map((b) => (
              <ParentApprovalItem key={b.id} item={b} onSeleccionar={() => undefined} />
            ))}
          </ul>
        </Seccion>
      </main>
    </div>
  );
}
