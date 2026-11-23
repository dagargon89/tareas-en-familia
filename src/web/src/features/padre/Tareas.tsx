import { Camera, EyeOff, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import type { PerfilPublico, Tarea } from '../../api/ApiClient.ts';
import { usePerfilesAdmin } from '../../hooks/usePerfilesAdmin.ts';
import { useCategorias, useDesactivarTarea, useGuardarTarea, useTareasAdmin } from '../../hooks/useTareasAdmin.ts';
import { detalleError } from '../../lib/errores.ts';
import { fechaLocal } from '../../lib/fechas.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Cargando } from '../../ui/Cargando.tsx';
import { Icono } from '../../ui/Icono.tsx';
import { FormTarea } from './FormTarea.tsx';
import { cuandoToca } from './validarTarea.ts';

interface VistaProps {
  tareas: Tarea[];
  perfiles: PerfilPublico[];
  perfilId: number | null;
  verInactivas: boolean;
  colorDeCategoria: (id: number) => Parameters<typeof Icono>[0]['color'];
  onFiltrar: (perfilId: number | null) => void;
  onVerInactivas: (v: boolean) => void;
  onNueva: () => void;
  onEditar: (t: Tarea) => void;
  onDesactivar: (t: Tarea) => void;
}

export function TareasVista({ tareas, perfiles, perfilId, verInactivas, colorDeCategoria, onFiltrar, onVerInactivas, onNueva, onEditar, onDesactivar }: VistaProps) {
  const apodo = (id: number) => perfiles.find((p) => p.id === id)?.apodo ?? '—';
  const filtro = (activo: boolean) => `min-h-tactil rounded-boton px-4 text-etiqueta ${activo ? 'bg-indigo-600 text-blanco' : 'bg-superficie'}`;
  return (
    <section className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-4">
        <h1 className="m-0 text-display">Tareas</h1>
        <Boton icono={<Plus className="size-5" aria-hidden="true" />} onClick={onNueva}>
          Nueva
        </Boton>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <button type="button" className={filtro(perfilId === null)} onClick={() => onFiltrar(null)}>
          Todas
        </button>
        {perfiles.map((p) => (
          <button key={p.id} type="button" className={filtro(perfilId === p.id)} onClick={() => onFiltrar(p.id)}>
            {p.apodo}
          </button>
        ))}
        <label className="ml-auto flex items-center gap-2 text-etiqueta text-tinta-600">
          <input type="checkbox" checked={verInactivas} onChange={(e) => onVerInactivas(e.target.checked)} /> Ver desactivadas
        </label>
      </div>
      {tareas.length === 0 ? (
        <p className="m-0 rounded-tarjeta bg-superficie p-6 text-cuerpo text-tinta-600">No hay tareas con este filtro.</p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-3 p-0">
          {tareas.map((t) => (
            <li key={t.id} className={`flex flex-wrap items-center gap-4 rounded-tarjeta bg-superficie p-4 ${t.activa ? '' : 'opacity-60'}`}>
              <Icono clave={t.icono} color={colorDeCategoria(t.categoria_id)} />
              <div className="flex min-w-0 flex-1 flex-col">
                <span className="text-titulo">
                  {t.titulo} {t.requiere_foto && <Camera className="inline size-4 text-tinta-600" aria-label="pide foto" />}
                </span>
                <span className="text-etiqueta text-tinta-600">
                  {apodo(t.perfil_id)} · {cuandoToca(t)} · {t.puntos} pts{t.creada_por === 'perfil' ? ' · creada por ella' : ''}
                </span>
              </div>
              <div className="flex gap-1">
                <button type="button" onClick={() => onEditar(t)} className="inline-flex min-h-tactil items-center gap-1 rounded-boton px-3 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
                  <Pencil className="size-4" aria-hidden="true" /> Editar
                </button>
                {t.activa && (
                  <button type="button" onClick={() => onDesactivar(t)} className="inline-flex min-h-tactil items-center gap-1 rounded-boton px-3 text-etiqueta text-tinta-600 hover:bg-blanco">
                    <EyeOff className="size-4" aria-hidden="true" /> Desactivar
                  </button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

export function Tareas() {
  const hoy = fechaLocal();
  const [perfilId, setPerfilId] = useState<number | null>(null);
  const [verInactivas, setVerInactivas] = useState(false);
  const [editando, setEditando] = useState<Tarea | 'nueva' | null>(null);

  const tareas = useTareasAdmin({ perfil_id: perfilId ?? undefined, activa: verInactivas ? undefined : true });
  const perfiles = usePerfilesAdmin();
  const categorias = useCategorias();
  const guardar = useGuardarTarea();
  const desactivar = useDesactivarTarea();

  if (tareas.isPending || perfiles.isPending || categorias.isPending) return <Cargando />;
  if (tareas.isError || perfiles.isError || categorias.isError) {
    return <p className="text-cuerpo text-indigo-700">No se pudieron cargar las tareas. Intenta de nuevo.</p>;
  }

  const activos = perfiles.data.filter((p) => p.activo);

  if (editando !== null) {
    const tarea = editando === 'nueva' ? null : editando;
    return (
      <section className="flex flex-col gap-6">
        <h1 className="m-0 text-display">{tarea ? 'Editar tarea' : 'Nueva tarea'}</h1>
        <FormTarea
          key={tarea?.id ?? 'nueva'}
          tarea={tarea}
          perfiles={activos}
          categorias={categorias.data}
          hoy={hoy}
          perfilInicial={perfilId}
          enviando={guardar.isPending}
          erroresServidor={guardar.error ? (detalleError(guardar.error).campos ?? {}) : {}}
          onCancelar={() => {
            guardar.reset();
            setEditando(null);
          }}
          onGuardar={(datos) => guardar.mutate({ id: tarea?.id ?? null, datos }, { onSuccess: () => setEditando(null) })}
        />
      </section>
    );
  }

  return (
    <TareasVista
      tareas={tareas.data}
      perfiles={activos}
      perfilId={perfilId}
      verInactivas={verInactivas}
      colorDeCategoria={(id) => categorias.data.find((c) => c.id === id)?.color ?? 'lavanda'}
      onFiltrar={setPerfilId}
      onVerInactivas={setVerInactivas}
      onNueva={() => setEditando('nueva')}
      onEditar={setEditando}
      onDesactivar={(t) => {
        if (window.confirm(`¿Desactivar "${t.titulo}"? Se quitan los días pendientes que vienen; el historial se conserva.`)) {
          desactivar.mutate(t.id);
        }
      }}
    />
  );
}
