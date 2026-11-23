import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useRef, useState } from 'react';
import { useSearchParams } from 'react-router';
import type { Dia, Instancia, NivelUx } from '../../api/ApiClient.ts';
import { useMarcarHecha, useMiDia } from '../../hooks/useMiDia.ts';
import { useSesion } from '../../hooks/useSesion.ts';
import { detalleError } from '../../lib/errores.ts';
import { diasEntre, fechaLarga, fechaLocal, sumarDias } from '../../lib/fechas.ts';
import { sonar } from '../../lib/sonidos.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { Cargando } from '../../ui/Cargando.tsx';
import { celebrar } from './Celebracion.ts';
import { TaskCard } from './TaskCard.tsx';

/** RF-04: se puede ir hasta 7 días atrás y 7 adelante. */
export const RANGO_DIAS = 7;

interface VistaProps {
  dia: Dia;
  hoy: string;
  apodo: string;
  nivel: NivelUx;
  marcando: number | null;
  aviso: string | null;
  onCambiarDia: (fecha: string) => void;
  onListo: (i: Instancia, origen: { x: number; y: number }) => void;
}

/** Vista de "Mi día" (RF-04, RF-05, RF-13). */
export function MiDiaVista({ dia, hoy, apodo, nivel, marcando, aviso, onCambiarDia, onListo }: VistaProps) {
  const v = VOCABULARIO[nivel];
  const desplazamiento = diasEntre(hoy, dia.fecha);
  const esFuturo = desplazamiento > 0;
  const titulo = dia.es_hoy ? `¡Hola, ${apodo}!` : desplazamiento === -1 ? 'Ayer' : desplazamiento === 1 ? 'Mañana' : fechaLarga(dia.fecha);
  const { total, hechas } = dia.resumen;
  const flecha = 'inline-flex size-tactil items-center justify-center rounded-full bg-superficie text-indigo-700 disabled:opacity-30';

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

      {esFuturo && total > 0 && (
        <p className="m-0 text-etiqueta text-tinta-600">Estas {v.tareas.toLowerCase()} se pueden marcar cuando llegue el día.</p>
      )}

      <p aria-live="polite" className="m-0 empty:hidden rounded-tarjeta-interna bg-lavanda-100 p-3 text-cuerpo text-indigo-700">
        {aviso}
      </p>

      {total === 0 ? (
        <p className="m-0 rounded-tarjeta bg-superficie p-8 text-center text-cuerpo">{v.diaVacio}</p>
      ) : (
        <div className="flex flex-col gap-4">
          {dia.instancias.map((i) => (
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

  if (dia.isPending) return <Cargando />;
  if (dia.isError) return <p className="rounded-tarjeta bg-lavanda-100 p-6 text-cuerpo text-indigo-700">No pudimos cargar tu día. Revisa el internet e intenta de nuevo.</p>;

  return (
    <>
      <MiDiaVista
        dia={dia.data}
        hoy={hoy}
        apodo={sesion?.perfil_activo?.apodo ?? ''}
        nivel={nivel}
        marcando={marcar.isPending ? (marcar.variables?.instancia.id ?? null) : null}
        aviso={aviso}
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
        capture="environment"
        className="hidden"
        onChange={(e) => {
          const archivo = e.target.files?.[0];
          const p = pendienteFoto.current;
          e.target.value = '';
          pendienteFoto.current = null;
          if (!archivo || !p) return;
          if (archivo.size > 3 * 1024 * 1024) {
            setAviso('La foto es muy grande (máximo 3 MB). Prueba con otra.');
            return;
          }
          enviar(p.i, p.origen, archivo);
        }}
      />
    </>
  );
}
