import { useState, type ReactNode } from 'react';
import type { Instancia, NivelUx } from '../../api/ApiClient.ts';
import { diasEntre, fechaLocal } from '../../lib/fechas.ts';
import { sonar } from '../../lib/sonidos.ts';
import { NIVELES, NOMBRE_NIVEL } from '../../niveles/nivel.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { FormasVivas } from '../../ui/FormasVivas.tsx';
import { AvatarSelector } from '../acceso/AvatarSelector.tsx';
import { EntrarPerfilVista } from '../acceso/EntrarPerfil.tsx';
import { LoginPadreVista } from '../acceso/LoginPadre.tsx';
import { SelectorPerfilVista } from '../acceso/SelectorPerfil.tsx';
import { FormPerfil } from '../padre/FormPerfil.tsx';
import { PerfilesVista } from '../padre/Perfiles.tsx';
import { PinPad } from '../acceso/PinPad.tsx';
import { celebrar } from '../mi-dia/Celebracion.ts';
import { MiDiaVista } from '../mi-dia/MiDia.tsx';
import { FormTarea } from '../padre/FormTarea.tsx';
import { TareasVista } from '../padre/Tareas.tsx';
import { ParentApprovalItem } from '../padre/ParentApprovalItem.tsx';
import { RewardCard } from '../premios/RewardCard.tsx';
import { BadgeGrid } from '../progreso/BadgeGrid.tsx';
import { FamilyGoalBar } from '../progreso/FamilyGoalBar.tsx';
import { StatTile } from '../progreso/StatTile.tsx';
import { StreakBadge } from '../progreso/StreakBadge.tsx';
import { XpWaveBar } from '../progreso/XpWaveBar.tsx';
import { BANDEJA, CATEGORIAS, TAREAS, INSIGNIAS, INSTANCIAS, META, NIVEL_ACTUAL, NIVEL_SIGUIENTE, PERFILES, PERFILES_ADMIN, RECOMPENSAS } from './datosGaleria.ts';

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

/** Encierra una pantalla completa: el transform hace que sus elementos fixed queden dentro. */
function Marco({ titulo, alto = 640, children }: { titulo: string; alto?: number; children: ReactNode }) {
  return (
    <figure className="m-0 flex flex-col gap-2">
      <figcaption className="text-etiqueta text-tinta-600">{titulo}</figcaption>
      <div className="relative overflow-auto rounded-tarjeta border-2 border-superficie bg-fondo [transform:translateZ(0)]" style={{ height: alto }}>
        {children}
      </div>
    </figure>
  );
}

/** Galería de componentes con datos fijos (solo desarrollo, ADR-008). */
export function Galeria() {
  const { nivel, setNivel } = useSesionStore();
  const hoy = fechaLocal();
  const [fechaDia, setFechaDia] = useState(hoy);
  const [instancias, setInstancias] = useState<Instancia[]>(INSTANCIAS);
  const [fallos, setFallos] = useState(0);
  const [pinMsg, setPinMsg] = useState<string | null>(null);
  const [pedidas, setPedidas] = useState<number[]>([]);
  const v = VOCABULARIO[nivel];

  function marcar(i: Instancia, origen: { x: number; y: number }) {
    setInstancias((xs) => xs.map((x) => (x.id === i.id ? { ...x, estado: 'hecha', mensaje_devolucion: null } : x)));
    celebrar(nivel, origen);
    sonar('completar', true);
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

        <Seccion titulo="Sprint 1 · pantallas de acceso" nota="Vistas reales con datos fijos; en la app se conectan a la API">
          <Marco titulo="/login · Login del padre (RF-02)">
            <LoginPadreVista enviando={false} error="Correo o contraseña incorrectos." onEntrar={() => undefined} />
          </Marco>
          <Marco titulo="/ · Selector de perfil (RF-01)">
            <SelectorPerfilVista perfiles={PERFILES} onElegir={() => undefined} onModoPadre={() => undefined} />
          </Marco>
          <Marco titulo="/entrar/2 · PIN de la hija (usa el nivel elegido arriba)" alto={760}>
            <EntrarPerfilVista
              perfil={{ ...PERFILES[1], nivel_ux: nivel }}
              ocupado={false}
              fallos={fallos}
              error={pinMsg}
              bloqueado={null}
              onVolver={() => undefined}
              onPin={(pin) => {
                if (pin === '2580') setPinMsg('¡Bienvenida!');
                else {
                  setFallos((f) => f + 1);
                  setPinMsg(v.pinIncorrecto);
                }
              }}
            />
          </Marco>
          <Marco titulo="/padre/perfiles · Lista (RF-17)" alto={520}>
            <div className="p-4">
              <PerfilesVista perfiles={PERFILES_ADMIN} onNuevo={() => undefined} onEditar={() => undefined} onDesbloquear={() => undefined} onDesactivar={() => undefined} onBorrar={() => undefined} />
            </div>
          </Marco>
          <Marco titulo="/padre/perfiles · Nuevo perfil (prueba un PIN como 1234)" alto={900}>
            <div className="p-4">
              <FormPerfil perfil={null} enviando={false} onGuardar={() => undefined} onCancelar={() => undefined} />
            </div>
          </Marco>
        </Seccion>

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

        <Seccion titulo="Sprint 2 · Mi día" nota="RF-04 y RF-05 · toca ¡Listo! (con sonido); las flechas cambian de día hasta ±7; en días futuros no se puede marcar">
          <Marco titulo="/hoy" alto={820}>
            <div className="p-4">
              <MiDiaVista
                dia={{
                  fecha: fechaDia,
                  es_hoy: fechaDia === hoy,
                  instancias: diasEntre(hoy, fechaDia) === 0 ? instancias : diasEntre(hoy, fechaDia) % 2 === 0 ? [] : instancias.slice(0, 3).map((i) => ({ ...i, estado: 'pendiente' as const, mensaje_devolucion: null })),
                  resumen: (() => {
                    const xs = diasEntre(hoy, fechaDia) === 0 ? instancias : diasEntre(hoy, fechaDia) % 2 === 0 ? [] : instancias.slice(0, 3);
                    const hechas = diasEntre(hoy, fechaDia) === 0 ? xs.filter((x) => x.estado === 'hecha' || x.estado === 'aprobada').length : 0;
                    return { total: xs.length, hechas, aprobadas: 0 };
                  })(),
                }}
                hoy={hoy}
                apodo="Sofi"
                nivel={nivel}
                marcando={null}
                aviso={null}
                onCambiarDia={setFechaDia}
                onListo={marcar}
              />
            </div>
          </Marco>
        </Seccion>

        <Seccion titulo="Sprint 2 · Tareas del padre" nota="RF-03 y RF-13 · prueba tipo Escolar o Actividad con 0 puntos">
          <Marco titulo="/padre/tareas · Lista" alto={520}>
            <div className="p-4">
              <TareasVista
                tareas={TAREAS}
                perfiles={PERFILES}
                perfilId={null}
                verInactivas={false}
                colorDeCategoria={(id) => CATEGORIAS.find((c) => c.id === id)?.color ?? 'lavanda'}
                onFiltrar={() => undefined}
                onVerInactivas={() => undefined}
                onNueva={() => undefined}
                onEditar={() => undefined}
                onDesactivar={() => undefined}
              />
            </div>
          </Marco>
          <Marco titulo="/padre/tareas · Nueva tarea" alto={900}>
            <div className="p-4">
              <FormTarea tarea={null} perfiles={PERFILES} categorias={CATEGORIAS} hoy={hoy} enviando={false} onGuardar={() => undefined} onCancelar={() => undefined} />
            </div>
          </Marco>
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
