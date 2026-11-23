import { useState, type ReactNode } from 'react';
import type { Instancia, NivelUx } from '../../api/ApiClient.ts';
import { diasEntre, fechaLocal, lunesDe, sumarDias } from '../../lib/fechas.ts';
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
import { WeekCalendar } from '../calendario/WeekCalendar.tsx';
import { MiDiaVista } from '../mi-dia/MiDia.tsx';
import { FormTareaPro } from '../mis-tareas/FormTareaPro.tsx';
import { PanelVista } from '../padre/Panel.tsx';
import { FormTarea } from '../padre/FormTarea.tsx';
import { TareasVista } from '../padre/Tareas.tsx';
import { FormEscolarPropia } from '../mis-tareas/FormEscolarPropia.tsx';
import { MisTareasVista } from '../mis-tareas/MisTareas.tsx';
import { AprobacionesVista } from '../padre/Aprobaciones.tsx';
import { resumirResultados } from '../padre/resumirResultados.ts';
import { MisLogrosVista } from '../progreso/MisLogros.tsx';
import { PremiosVista } from '../premios/Premios.tsx';
import { ConfiguracionVista } from '../padre/Configuracion.tsx';
import { MetasVista } from '../padre/Metas.tsx';
import { RecompensasVista } from '../padre/Recompensas.tsx';
import { CANJES, RECOMPENSAS_ADMIN, BANDEJA, CATEGORIAS, TAREAS, INSIGNIAS, INSTANCIAS, META, NIVEL_ACTUAL, NIVEL_SIGUIENTE, PERFILES, PERFILES_ADMIN, RECOMPENSAS } from './datosGaleria.ts';

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

/** Foto de evidencia de ejemplo (SVG en línea; la CSP permite data: en img-src). */
const FOTO_EJEMPLO =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 60"><rect width="60" height="60" fill="#C9EFD5"/><rect x="12" y="30" width="36" height="16" rx="4" fill="#3F4FB8"/><circle cx="22" cy="20" r="7" fill="#FFE08A"/></svg>');

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
  const [soloDeEllas, setSoloDeEllas] = useState(false);
  const [bandeja, setBandeja] = useState(BANDEJA);
  const [seleccion, setSeleccion] = useState<number[]>([]);
  const [devolviendo, setDevolviendo] = useState<number | null>(null);
  const [avisoBandeja, setAvisoBandeja] = useState<string | null>(null);
  const aprobarEnGaleria = (ids: number[]) => {
    const rs = bandeja.filter((b) => ids.includes(b.id)).map((b) => ({ instancia: { ...b, estado: 'aprobada' as const }, puntos_acreditados: b.puntos, insignias_nuevas: [], meta_cumplida: false }));
    setAvisoBandeja(resumirResultados(rs, bandeja));
    setBandeja((xs) => xs.filter((x) => !ids.includes(x.id)));
    setSeleccion([]);
  };
  const [instancias, setInstancias] = useState<Instancia[]>(INSTANCIAS);
  const [fallos, setFallos] = useState(0);
  const [pinMsg, setPinMsg] = useState<string | null>(null);
  const [pedidas, setPedidas] = useState<number[]>([]);
  const [editandoPremio, setEditandoPremio] = useState<(typeof RECOMPENSAS_ADMIN)[number] | 'nueva' | null>(null);
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

        <Seccion titulo="RF-19 · Tareas escolares propias" nota="Aventureras y Pro agregan lo que les dejaron en la escuela; el padre lo ve con la marca «Creada por…»">
          <Marco titulo="/mis-tareas" alto={460}>
            <div className="p-4">
              <MisTareasVista tareas={TAREAS.filter((t) => t.creada_por === 'perfil')} hoy={hoy} aviso={null} onEditar={() => undefined} onDesactivar={() => undefined} />
            </div>
          </Marco>
          <Marco titulo="/mis-tareas/nueva" alto={900}>
            <div className="p-4">
              <FormEscolarPropia tarea={null} hoy={hoy} enviando={false} onGuardar={() => undefined} onCancelar={() => undefined} />
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
                soloDeEllas={soloDeEllas}
                onSoloDeEllas={setSoloDeEllas}
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

        <Seccion titulo="Sprint 3 · Mis logros" nota="RF-08, RF-10, RF-11 y RF-12 · cambia de nivel arriba">
          <Marco titulo="/logros" alto={860}>
            <div className="p-4">
              <MisLogrosVista
                nivel={nivel}
                perfiles={PERFILES}
                progreso={{ xp: 612, saldo: 128, disponible: 88, nivel: NIVEL_ACTUAL, siguiente_nivel: NIVEL_SIGUIENTE, racha_semanas: 3, semana_actual: { asignadas: 12, aprobadas: 9, porcentaje: 75 }, insignias: INSIGNIAS, meta_familiar: META }}
              />
            </div>
          </Marco>
        </Seccion>

        <Seccion titulo="Sprint 4 · Premios de la hija" nota="RF-09 · lo que ya alcanza va primero; toca Canjear">
          <Marco titulo="/premios" alto={820}>
            <div className="p-4">
              <PremiosVista
                recompensas={RECOMPENSAS}
                canjes={[...CANJES, ...pedidas.map((id, i) => ({ id: 100 + i, perfil_id: 2, recompensa: RECOMPENSAS.find((r) => r.id === id)!, costo: RECOMPENSAS.find((r) => r.id === id)!.costo, estado: 'solicitado' as const, created_at: new Date().toISOString() }))]}
                disponible={88}
                nivel={nivel}
                pidiendo={null}
                aviso={pedidas.length ? '¡Listo! Papá o mamá lo revisan.' : null}
                onCanjear={(r) => {
                  sonar('canje', true);
                  setPedidas((p) => [...p, r.id]);
                }}
              />
            </div>
          </Marco>
        </Seccion>

        <Seccion titulo="Sprint 4 · Modo padre" nota="RF-09, RF-12 y RF-18">
          <Marco titulo="/padre/premios" alto={820}>
            <div className="p-4">
              <RecompensasVista
                canjes={CANJES}
                recompensas={RECOMPENSAS_ADMIN}
                perfiles={PERFILES}
                ocupado={false}
                aviso={null}
                editando={editandoPremio}
                guardando={false}
                onRevisar={() => undefined}
                onEditar={setEditandoPremio}
                onGuardar={() => setEditandoPremio(null)}
              />
            </div>
          </Marco>
          <Marco titulo="/padre/metas" alto={520}>
            <div className="p-4">
              <MetasVista metas={[META, { ...META, id: 2, titulo: 'Paseo al parque', estado: 'cumplida', progreso_puntos: 300, objetivo_puntos: 300 }]} perfiles={PERFILES} hoy={hoy} editando={null} guardando={false} aviso={null} onEditar={() => undefined} onGuardar={() => undefined} onCancelarMeta={() => undefined} />
            </div>
          </Marco>
          <Marco titulo="/padre/ajustes (prueba un PIN como 1111)" alto={820}>
            <div className="p-4">
              <ConfiguracionVista config={{ racha_umbral: 80, sonidos_default: true, dias_generacion: 7, evidencia_dias_retencion: 30, puntos_tarea_propia: 5 }} enviando={false} aviso={null} onGuardar={() => undefined} />
            </div>
          </Marco>
        </Seccion>

        <Seccion titulo="Sprint 3 · Aprobaciones" nota="RF-07 · aprueba una, varias en lote, o devuelve con mensaje">
          <Marco titulo="/padre/aprobaciones" alto={620}>
            <div className="p-4">
              <AprobacionesVista
                items={bandeja}
                seleccion={seleccion}
                devolviendo={devolviendo}
                ocupado={false}
                aviso={avisoBandeja}
                urlFoto={() => FOTO_EJEMPLO}
                onSeleccionar={(id, v) => setSeleccion((xs) => (v ? [...xs, id] : xs.filter((x) => x !== id)))}
                onSeleccionarTodas={(v) => setSeleccion(v ? bandeja.map((b) => b.id) : [])}
                onAprobar={(id) => aprobarEnGaleria([id])}
                onAprobarSeleccion={() => aprobarEnGaleria(seleccion)}
                onAbrirDevolver={setDevolviendo}
                onDevolver={(id) => {
                  setBandeja((xs) => xs.filter((x) => x.id !== id));
                  setDevolviendo(null);
                  setAvisoBandeja('Devuelta con tu mensaje.');
                }}
              />
              <button type="button" onClick={() => { setBandeja(BANDEJA); setAvisoBandeja(null); }} className="mt-3 rounded-boton px-4 py-2 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
                Reiniciar bandeja
              </button>
            </div>
          </Marco>
        </Seccion>
        <Seccion titulo="Sprint 5 · Semana, Pro y panel" nota="RF-14, RF-15 y RF-16">
          <Marco titulo="/calendario (en modo padre, con el avatar de cada hija)" alto={620}>
            <div className="p-4">
              <WeekCalendar
                semana={{
                  desde: lunesDe(hoy),
                  hasta: sumarDias(lunesDe(hoy), 6),
                  dias: Array.from({ length: 7 }, (_, n) => {
                    const fecha = sumarDias(lunesDe(hoy), n);
                    const instancias = INSTANCIAS.filter((_, k) => (k + n) % 3 === 0).map((i, k) => ({ ...i, id: n * 10 + k, fecha, perfil_id: ((n + k) % 3) + 1 }));
                    return { fecha, es_hoy: fecha === hoy, instancias, resumen: { total: instancias.length, hechas: 0, aprobadas: 0 } };
                  }),
                }}
                hoy={hoy}
                perfiles={PERFILES}
                onSemana={() => undefined}
              />
            </div>
          </Marco>
          <Marco titulo="/mis-tareas/nueva (Pro: escuela, casa o actividad)" alto={760}>
            <div className="p-4">
              <FormTareaPro tarea={null} hoy={hoy} enviando={false} onGuardar={() => undefined} onCancelar={() => undefined} />
            </div>
          </Marco>
          <Marco titulo="/padre · Panel" alto={760}>
            <div className="p-4">
              <PanelVista
                perfiles={PERFILES}
                panel={{
                  semana_iso: '2026-W48',
                  hijas: PERFILES.map((p, k) => ({ perfil: p, porcentaje_semana: [40, 75, 90][k], por_aprobar: [2, 1, 0][k], racha_semanas: [0, 3, 5][k], disponible: [30, 88, 140][k], proximas_entregas: k === 1 ? [{ ...INSTANCIAS[1], titulo: 'Maqueta del sistema solar', materia: 'Ciencias' }] : [] })),
                  meta_familiar: META,
                  canjes_pendientes: CANJES.filter((c) => c.estado === 'solicitado'),
                }}
              />
            </div>
          </Marco>
        </Seccion>
      </main>
    </div>
  );
}
