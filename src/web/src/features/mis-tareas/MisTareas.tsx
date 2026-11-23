import { ArrowLeft, EyeOff, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import type { NivelUx, Tarea } from '../../api/ApiClient.ts';
import { useDesactivarTareaPropia, useGuardarTareaPropia, useMisTareas } from '../../hooks/useMisTareas.ts';
import { detalleError } from '../../lib/errores.ts';
import { fechaCorta, fechaLocal } from '../../lib/fechas.ts';
import { Cargando } from '../../ui/Cargando.tsx';
import { Icono } from '../../ui/Icono.tsx';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { cuandoToca } from '../padre/validarTarea.ts';
import { FormEscolarPropia } from './FormEscolarPropia.tsx';
import { FormTareaPro } from './FormTareaPro.tsx';

interface VistaProps {
  tareas: Tarea[];
  hoy: string;
  nivel?: NivelUx;
  aviso: string | null;
  onEditar: (t: Tarea) => void;
  onDesactivar: (t: Tarea) => void;
}

/** Lista de tareas creadas por la hija (RF-19; en Pro también RF-15). */
export function MisTareasVista({ tareas, hoy, nivel = 'aventureras', aviso, onEditar, onDesactivar }: VistaProps) {
  const esPro = nivel === 'pro';
  const activas = tareas.filter((t) => t.activa);
  const proximas = activas.filter((t) => !t.fecha_entrega || t.fecha_entrega >= hoy);
  const pasadas = activas.filter((t) => t.fecha_entrega && t.fecha_entrega < hoy);
  const fila = (t: Tarea) => (
    <li key={t.id} className="flex flex-wrap items-center gap-4 rounded-tarjeta bg-superficie p-4">
      <Icono clave={t.icono} color="menta" />
      <div className="flex min-w-0 flex-1 flex-col">
        <span className="text-titulo">{t.titulo}</span>
        <span className="text-etiqueta text-tinta-600">
          {t.tipo === 'escolar' ? (
            <>
              {t.materia}
              {t.fecha_entrega && ` · entrega ${fechaCorta(t.fecha_entrega)}`}
            </>
          ) : (
            cuandoToca(t)
          )}{' '}
          · {t.puntos} pts
        </span>
      </div>
      <div className="flex gap-1">
        <button type="button" onClick={() => onEditar(t)} className="inline-flex min-h-tactil items-center gap-1 rounded-boton px-3 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
          <Pencil className="size-4" aria-hidden="true" /> Editar
        </button>
        <button type="button" onClick={() => onDesactivar(t)} className="inline-flex min-h-tactil items-center gap-1 rounded-boton px-3 text-etiqueta text-tinta-600 hover:bg-tarjeta" aria-label={`Quitar ${t.titulo}`}>
          <EyeOff className="size-4" aria-hidden="true" /> Quitar
        </button>
      </div>
    </li>
  );

  return (
    <section className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="m-0 text-display">{esPro ? 'Mis tareas' : 'Mis tareas de la escuela'}</h1>
        <Link to="/mis-tareas/nueva" className="inline-flex min-h-tactil items-center gap-2 rounded-boton bg-indigo-600 px-5 text-titulo text-blanco">
          <Plus className="size-5" aria-hidden="true" /> Agregar
        </Link>
      </div>
      <p aria-live="polite" className="m-0 empty:hidden rounded-tarjeta-interna bg-lavanda-100 p-3 text-cuerpo text-indigo-700">
        {aviso}
      </p>
      {proximas.length === 0 ? (
        <p className="m-0 rounded-tarjeta bg-superficie p-6 text-cuerpo text-tinta-600">
          {esPro
            ? 'Aquí aparecen las tareas que tú agregues: de la escuela, de la casa o actividades.'
            : 'Aquí aparecen las tareas que tú agregues. Cuando te dejen algo en la escuela, agrégalo para que papá o mamá lo vean.'}
        </p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-3 p-0">{proximas.map(fila)}</ul>
      )}
      {pasadas.length > 0 && (
        <details className="flex flex-col gap-3">
          <summary className="cursor-pointer text-etiqueta text-tinta-600">Ya pasó la entrega ({pasadas.length})</summary>
          <ul className="m-0 mt-3 flex list-none flex-col gap-3 p-0">{pasadas.map(fila)}</ul>
        </details>
      )}
    </section>
  );
}

export function MisTareas() {
  const hoy = fechaLocal();
  const tareas = useMisTareas();
  const desactivar = useDesactivarTareaPropia();
  const navegar = useNavigate();
  const [aviso, setAviso] = useState<string | null>(null);
  const nivel = useSesionStore((s) => s.nivel);

  if (tareas.isPending) return <Cargando />;
  if (tareas.isError) return <p className="rounded-tarjeta bg-lavanda-100 p-6 text-cuerpo text-indigo-700">No pudimos cargar tus tareas. Intenta de nuevo.</p>;

  return (
    <MisTareasVista
      tareas={tareas.data}
      hoy={hoy}
      nivel={nivel}
      aviso={aviso}
      onEditar={(t) => navegar(`/mis-tareas/${t.id}`)}
      onDesactivar={(t) => {
        if (!window.confirm(`¿Quitar "${t.titulo}"?`)) return;
        setAviso(null);
        desactivar.mutate(t.id, {
          onError: (e) => setAviso(detalleError(e).codigo === 'ESTADO_INVALIDO' ? 'Esa tarea ya fue aprobada; ya no se puede quitar.' : 'No se pudo quitar. Intenta otra vez.'),
        });
      }}
    />
  );
}

/** Alta (/mis-tareas/nueva) y edición (/mis-tareas/:id) de una escolar propia. */
export function EditarTareaPropia() {
  const { id } = useParams();
  const hoy = fechaLocal();
  const navegar = useNavigate();
  const tareas = useMisTareas();
  const guardar = useGuardarTareaPropia();
  const nivel = useSesionStore((s) => s.nivel);
  const esNueva = id === undefined;

  if (!esNueva && tareas.isPending) return <Cargando />;
  const tarea = esNueva ? null : (tareas.data?.find((t) => t.id === Number(id)) ?? null);
  if (!esNueva && !tarea) {
    return (
      <p className="text-cuerpo">
        No encontramos esa tarea. <Link to="/mis-tareas" className="text-indigo-700 underline">Ver mis tareas</Link>
      </p>
    );
  }

  const d = guardar.error ? detalleError(guardar.error) : null;
  const volver = () => navegar(-1);

  return (
    <section className="flex flex-col gap-5">
      <button type="button" onClick={volver} className="inline-flex min-h-11 items-center gap-2 self-start rounded-boton px-3 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
        <ArrowLeft className="size-5" aria-hidden="true" /> Volver
      </button>
      <h1 className="m-0 text-display">{tarea ? 'Editar tarea' : nivel === 'pro' ? 'Nueva tarea' : 'Nueva tarea de la escuela'}</h1>
      {d && !d.campos && (
        <p className="m-0 rounded-tarjeta-interna bg-lavanda-100 p-3 text-cuerpo text-indigo-700">
          {d.codigo === 'ESTADO_INVALIDO' ? 'Esa tarea ya fue aprobada; ya no se puede cambiar.' : 'No se pudo guardar. Intenta otra vez.'}
        </p>
      )}
      {nivel === 'pro' ? (
        <FormTareaPro
          key={tarea?.id ?? 'nueva'}
          tarea={tarea}
          hoy={hoy}
          enviando={guardar.isPending}
          erroresServidor={d?.campos ?? {}}
          onCancelar={volver}
          onGuardar={(datos) => guardar.mutate({ id: tarea?.id ?? null, datos }, { onSuccess: () => navegar('/mis-tareas', { replace: true }) })}
        />
      ) : (
        <FormEscolarPropia
          key={tarea?.id ?? 'nueva'}
          tarea={tarea}
          hoy={hoy}
          puntos={tarea?.puntos}
          enviando={guardar.isPending}
          erroresServidor={d?.campos ?? {}}
          onCancelar={volver}
          onGuardar={(datos) => guardar.mutate({ id: tarea?.id ?? null, datos }, { onSuccess: () => navegar('/mis-tareas', { replace: true }) })}
        />
      )}
      {!tarea && (
        <p className="m-0 text-etiqueta text-tinta-600">
          Aparece en tu día desde hoy hasta la fecha de entrega.
        </p>
      )}
    </section>
  );
}
