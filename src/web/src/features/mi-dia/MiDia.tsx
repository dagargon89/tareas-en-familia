import { BookOpen, Camera, ChevronLeft, ChevronRight, PartyPopper, Plus, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import type { Dia, Instancia, NivelUx } from '../../api/ApiClient.ts';
import { useMarcarHecha, useMiDia } from '../../hooks/useMiDia.ts';
import { useSesion } from '../../hooks/useSesion.ts';
import { comprimirImagen } from '../../lib/comprimirImagen.ts';
import { detalleError } from '../../lib/errores.ts';
import { diasEntre, fechaLarga, fechaLocal, sumarDias } from '../../lib/fechas.ts';
import { sonar } from '../../lib/sonidos.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { Boton } from '../../ui/Boton.tsx';
import { SkeletonLista } from '../../ui/Cargando.tsx';
import { EstadoError } from '../../ui/EstadoError.tsx';
import { LightboxFoto } from '../../ui/LightboxFoto.tsx';
import { Toast } from '../../ui/Toast.tsx';
import { celebrar } from './Celebracion.ts';
import { puedeCrearTareas } from '../mis-tareas/escolarPropia.ts';
import { TaskCard } from './TaskCard.tsx';

/** RF-04: se puede ir hasta 7 días atrás y 7 adelante. */
export const RANGO_DIAS = 7;

const ORDEN_ESTADO: Record<Instancia['estado'], number> = {
  devuelta: 0,
  pendiente: 1,
  hecha: 2,
  aprobada: 3,
};

interface VistaProps {
  dia: Dia;
  hoy: string;
  apodo: string;
  nivel: NivelUx;
  marcando: number | null;
  aviso: string | null;
  onCerrarAviso?: () => void;
  onCambiarDia: (fecha: string) => void;
  onListo: (i: Instancia, origen: { x: number; y: number }) => void;
}

/** Vista de "Mi día" (RF-04, RF-05, RF-13). */
export function MiDiaVista({ dia, hoy, apodo, nivel, marcando, aviso, onCerrarAviso, onCambiarDia, onListo }: VistaProps) {
  const v = VOCABULARIO[nivel];
  const desplazamiento = diasEntre(hoy, dia.fecha);
  const esFuturo = desplazamiento > 0;
  const titulo = dia.es_hoy ? `¡Hola, ${apodo}!` : desplazamiento === -1 ? 'Ayer' : desplazamiento === 1 ? 'Mañana' : fechaLarga(dia.fecha);
  const { total, hechas } = dia.resumen;
  const diaCompleto = total > 0 && hechas >= total;
  const ordenadas = [...dia.instancias].sort((a, b) => ORDEN_ESTADO[a.estado] - ORDEN_ESTADO[b.estado]);
  const flecha = 'inline-flex size-tactil items-center justify-center rounded-full bg-superficie text-indigo-700 transition-colors hover:bg-lavanda-100 disabled:opacity-30';

  return (
    <section className="flex flex-col gap-5">
      <header className="flex items-center gap-3">
        <button type="button" className={flecha} aria-label="Día anterior" disabled={desplazamiento <= -RANGO_DIAS} onClick={() => onCambiarDia(sumarDias(dia.fecha, -1))}>
          <ChevronLeft className="size-1/2" aria-hidden="true" />
        </button>
        <div className="flex min-w-0 flex-1 flex-col items-center text-center">
          <h1 className="m-0 text-display first-letter:uppercase">{titulo}</h1>
          <span className="text-etiqueta text-tinta-600 first-letter:uppercase">{fechaLarga(dia.fecha)}</span>
        </div>
        <button type="button" className={flecha} aria-label="Día siguiente" disabled={desplazamiento >= RANGO_DIAS} onClick={() => onCambiarDia(sumarDias(dia.fecha, 1))}>
          <ChevronRight className="size-1/2" aria-hidden="true" />
        </button>
      </header>

      {!dia.es_hoy && (
        <button type="button" onClick={() => onCambiarDia(hoy)} className="self-center rounded-boton px-4 py-1 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
          Volver a hoy
        </button>
      )}

      {puedeCrearTareas(nivel) && (
        <div className="flex flex-wrap gap-2">
          <Link to="/mis-tareas/nueva" className="inline-flex min-h-tactil flex-1 items-center justify-center gap-2 rounded-boton bg-lavanda-100 px-5 text-etiqueta text-indigo-700 transition hover:brightness-95">
            <Plus className="size-5" aria-hidden="true" /> Agregar tarea de la escuela
          </Link>
          <Link to="/mis-tareas" className="inline-flex min-h-tactil items-center justify-center gap-2 rounded-boton px-4 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
            <BookOpen className="size-5" aria-hidden="true" /> Mis tareas
          </Link>
        </div>
      )}

      {total > 0 && (
        <div className="flex flex-col gap-1" aria-label={`${hechas} de ${total} ${v.tareas.toLowerCase()} listas`}>
          <div className="h-3 overflow-hidden rounded-boton bg-lavanda-100">
            <div className="h-full rounded-boton bg-indigo-600 transition-[width] duration-500 ease-rebote" style={{ width: `${(hechas / total) * 100}%` }} />
          </div>
          <span className="text-etiqueta text-tinta-600">
            {hechas} de {total} {v.tareas.toLowerCase()} listas
          </span>
        </div>
      )}

      {diaCompleto && (
        <div className="flex items-center gap-3 rounded-tarjeta bg-menta-200 p-4 text-cuerpo text-tinta-900">
          <PartyPopper className="size-6 shrink-0 text-indigo-700" aria-hidden="true" />
          <span>
            {nivel === 'pro'
              ? 'Todas las tareas de este día están listas.'
              : '¡Increíble! Terminaste todas tus misiones de hoy.'}
          </span>
        </div>
      )}

      {esFuturo && total > 0 && (
        <p className="m-0 text-etiqueta text-tinta-600">Estas {v.tareas.toLowerCase()} se pueden marcar cuando llegue el día.</p>
      )}

      <Toast mensaje={aviso} sobreNav onCerrar={onCerrarAviso} />

      {total === 0 ? (
        <p className="m-0 rounded-tarjeta bg-superficie p-8 text-center text-cuerpo">{v.diaVacio}</p>
      ) : (
        <div className="flex flex-col gap-4">
          {ordenadas.map((i) => (
            <TaskCard key={i.id} instancia={i} nivel={nivel} puedeMarcar={!esFuturo} cargando={marcando === i.id} onListo={onListo} />
          ))}
        </div>
      )}
    </section>
  );
}

/** "Mi día" conectado a la API. La fecha vive en la URL (?fecha=) para poder volver atrás. */
export function MiDia() {
  const hoy = fechaLocal();
  const [params, setParams] = useSearchParams();
  const pedida = params.get('fecha');
  const fecha = pedida && /^\d{4}-\d{2}-\d{2}$/.test(pedida) && Math.abs(diasEntre(hoy, pedida)) <= RANGO_DIAS ? pedida : hoy;

  const { data: sesion } = useSesion();
  const { nivel, sonidos } = useSesionStore();
  const dia = useMiDia(fecha);
  const marcar = useMarcarHecha(fecha);
  const [aviso, setAviso] = useState<string | null>(null);
  const entradaFoto = useRef<HTMLInputElement>(null);
  const pendienteFoto = useRef<{ i: Instancia; origen: { x: number; y: number } } | null>(null);
  const [vistaPrevia, setVistaPrevia] = useState<{ i: Instancia; origen: { x: number; y: number }; archivo: File; url: string } | null>(null);

  useEffect(() => {
    const url = vistaPrevia?.url;
    return () => {
      if (url && typeof URL.revokeObjectURL === 'function') URL.revokeObjectURL(url);
    };
  }, [vistaPrevia?.url]);

  function enviar(i: Instancia, origen: { x: number; y: number }, foto?: File) {
    setAviso(null);
    marcar.mutate(
      { instancia: i, foto },
      {
        onSuccess: () => {
          celebrar(nivel, origen);
          sonar('completar', sonidos);
        },
        onError: (e) => setAviso(detalleError(e).codigo === 'VALIDACION' ? 'Esa foto no se pudo usar. Prueba con otra.' : 'No se pudo guardar. Intenta otra vez.'),
      },
    );
  }

  function alListo(i: Instancia, origen: { x: number; y: number }) {
    if (i.requiere_foto) {
      // RF-05: si requiere foto, primero cámara o galería.
      pendienteFoto.current = { i, origen };
      entradaFoto.current?.click();
      return;
    }
    enviar(i, origen);
  }

  if (dia.isPending) return <SkeletonLista filas={3} texto="Cargando tu día…" />;
  if (dia.isError) return <EstadoError mensaje="No pudimos cargar tu día. Revisa el internet e intenta de nuevo." onReintentar={() => void dia.refetch()} />;

  return (
    <>
      <MiDiaVista
        dia={dia.data}
        hoy={hoy}
        apodo={sesion?.perfil_activo?.apodo ?? ''}
        nivel={nivel}
        marcando={marcar.isPending ? (marcar.variables?.instancia.id ?? null) : null}
        aviso={aviso}
        onCerrarAviso={() => setAviso(null)}
        onCambiarDia={(f) => {
          setAviso(null);
          setParams(f === hoy ? {} : { fecha: f }, { replace: true });
        }}
        onListo={alListo}
      />
      <input
        ref={entradaFoto}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="hidden"
        onChange={async (e) => {
          const original = e.target.files?.[0];
          const p = pendienteFoto.current;
          e.target.value = '';
          pendienteFoto.current = null;
          if (!original || !p) return;
          const archivo = await comprimirImagen(original);
          if (archivo.size > 3 * 1024 * 1024) {
            setAviso('La foto es muy grande (máximo 3 MB). Prueba con otra.');
            return;
          }
          if (typeof URL.createObjectURL === 'function') {
            setVistaPrevia({ ...p, archivo, url: URL.createObjectURL(archivo) });
          } else {
            enviar(p.i, p.origen, archivo);
          }
        }}
      />
      <LightboxFoto
        abierto={vistaPrevia !== null}
        titulo={vistaPrevia?.i.titulo ?? ''}
        subtitulo="¿Se ve bien tu foto?"
        src={vistaPrevia?.url ?? ''}
        alt={`Vista previa de ${vistaPrevia?.i.titulo ?? 'evidencia'}`}
        onCerrar={() => setVistaPrevia(null)}
        acciones={
          vistaPrevia && (
            <>
              <Boton
                variante="secundario"
                tamano="compacto"
                icono={<Camera className="size-4" aria-hidden="true" />}
                onClick={() => {
                  pendienteFoto.current = { i: vistaPrevia.i, origen: vistaPrevia.origen };
                  setVistaPrevia(null);
                  entradaFoto.current?.click();
                }}
              >
                Elegir otra
              </Boton>
              <Boton
                variante="primario"
                tamano="compacto"
                icono={<Send className="size-4" aria-hidden="true" />}
                onClick={() => {
                  const actual = vistaPrevia;
                  setVistaPrevia(null);
                  enviar(actual.i, actual.origen, actual.archivo);
                }}
              >
                Enviar foto
              </Boton>
            </>
          )
        }
      />
    </>
  );
}
