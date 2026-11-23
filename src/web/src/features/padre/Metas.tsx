import { Plus, Target } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { Meta, MetaEntrada, PerfilPublico } from '../../api/ApiClient.ts';
import { useGuardarMeta, useMetas } from '../../hooks/useMetasConfig.ts';
import { usePerfilesAdmin } from '../../hooks/usePerfilesAdmin.ts';
import { detalleError } from '../../lib/errores.ts';
import { fechaCorta, fechaLocal } from '../../lib/fechas.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';
import { Cargando } from '../../ui/Cargando.tsx';
import { FamilyGoalBar } from '../progreso/FamilyGoalBar.tsx';
import { validarMeta } from './validarJuego.ts';

export function FormMeta({ meta, hoy, enviando, erroresServidor = {}, onGuardar, onCancelar }: { meta: Meta | null; hoy: string; enviando: boolean; erroresServidor?: Record<string, string>; onGuardar: (m: MetaEntrada) => void; onCancelar: () => void }) {
  const [m, setM] = useState<MetaEntrada>({
    titulo: meta?.titulo ?? '',
    premio: meta?.premio ?? '',
    objetivo_puntos: meta?.objetivo_puntos ?? 300,
    fecha_inicio: meta?.fecha_inicio ?? hoy,
    fecha_limite: meta?.fecha_limite ?? null,
  });
  const [tocado, setTocado] = useState(false);
  const errores = validarMeta(m);
  const ver = (k: keyof MetaEntrada) => (tocado ? errores[k] : undefined) ?? erroresServidor[k];

  function enviar(e: FormEvent) {
    e.preventDefault();
    setTocado(true);
    if (Object.keys(errores).length === 0) onGuardar({ ...m, titulo: m.titulo.trim(), premio: m.premio.trim(), fecha_limite: m.fecha_limite || null });
  }

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-4 rounded-tarjeta bg-superficie p-5">
      <Campo etiqueta="Meta" placeholder="Ej. Juntar puntos entre todas" maxLength={60} value={m.titulo} onChange={(e) => setM({ ...m, titulo: e.target.value })} error={ver('titulo')} />
      <Campo etiqueta="Premio para toda la familia" placeholder="Ej. Noche de pizza y película" maxLength={80} value={m.premio} onChange={(e) => setM({ ...m, premio: e.target.value })} error={ver('premio')} />
      <Campo
        etiqueta="Puntos objetivo"
        type="number"
        min={1}
        value={Number.isNaN(m.objetivo_puntos) ? '' : m.objetivo_puntos}
        onChange={(e) => setM({ ...m, objetivo_puntos: e.target.valueAsNumber })}
        error={ver('objetivo_puntos')}
        ayuda="Suman los puntos de tareas aprobadas de todas desde el inicio."
      />
      <div className="grid gap-4 sm:grid-cols-2">
        <Campo etiqueta="Inicio" type="date" value={m.fecha_inicio} onChange={(e) => setM({ ...m, fecha_inicio: e.target.value })} error={ver('fecha_inicio')} />
        <Campo etiqueta="Fecha límite (opcional)" type="date" min={m.fecha_inicio} value={m.fecha_limite ?? ''} onChange={(e) => setM({ ...m, fecha_limite: e.target.value || null })} error={ver('fecha_limite')} />
      </div>
      <div className="flex justify-end gap-2">
        <Boton variante="suave" onClick={onCancelar}>
          Cancelar
        </Boton>
        <Boton type="submit" disabled={enviando}>
          {enviando ? 'Guardando…' : meta ? 'Guardar' : 'Crear meta'}
        </Boton>
      </div>
    </form>
  );
}

const ESTADO = { activa: 'En curso', cumplida: '¡Cumplida!', cancelada: 'Cancelada' } as const;

interface VistaProps {
  metas: Meta[];
  perfiles: PerfilPublico[];
  hoy: string;
  editando: Meta | 'nueva' | null;
  guardando: boolean;
  aviso: string | null;
  erroresServidor?: Record<string, string>;
  onEditar: (m: Meta | 'nueva' | null) => void;
  onGuardar: (id: number | null, datos: MetaEntrada) => void;
  onCancelarMeta: (m: Meta) => void;
}

/** Metas familiares (RF-12): una sola activa; la barra es común, sin comparar hermanas. */
export function MetasVista({ metas, perfiles, hoy, editando, guardando, aviso, erroresServidor, onEditar, onGuardar, onCancelarMeta }: VistaProps) {
  const activa = metas.find((m) => m.estado === 'activa') ?? null;
  const anteriores = metas.filter((m) => m.estado !== 'activa');
  return (
    <section className="flex flex-col gap-6">
      <div className="flex items-center justify-between gap-3">
        <h1 className="m-0 text-display">Meta familiar</h1>
        {!activa && editando === null && (
          <Boton icono={<Plus className="size-5" aria-hidden="true" />} onClick={() => onEditar('nueva')}>
            Nueva meta
          </Boton>
        )}
      </div>
      <p aria-live="polite" className="m-0 empty:hidden rounded-tarjeta-interna bg-lavanda-100 p-3 text-cuerpo text-indigo-700">
        {aviso}
      </p>

      {editando !== null && (
        <FormMeta
          key={editando === 'nueva' ? 'nueva' : editando.id}
          meta={editando === 'nueva' ? null : editando}
          hoy={hoy}
          enviando={guardando}
          erroresServidor={erroresServidor}
          onCancelar={() => onEditar(null)}
          onGuardar={(datos) => onGuardar(editando === 'nueva' ? null : editando.id, datos)}
        />
      )}

      {activa ? (
        <div className="flex flex-col gap-3">
          <FamilyGoalBar meta={activa} perfiles={perfiles} />
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-etiqueta text-tinta-600">
              Desde {fechaCorta(activa.fecha_inicio)}
              {activa.fecha_limite ? ` hasta ${fechaCorta(activa.fecha_limite)}` : ''}
            </span>
            {editando === null && (
              <>
                <button type="button" onClick={() => onEditar(activa)} className="ml-auto min-h-tactil rounded-boton px-4 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
                  Editar
                </button>
                <button type="button" onClick={() => onCancelarMeta(activa)} className="min-h-tactil rounded-boton px-4 text-etiqueta text-tinta-600 hover:bg-superficie">
                  Cancelar meta
                </button>
              </>
            )}
          </div>
        </div>
      ) : (
        editando === null && (
          <p className="m-0 flex items-center gap-2 rounded-tarjeta bg-superficie p-6 text-cuerpo text-tinta-600">
            <Target className="size-5" aria-hidden="true" /> No hay meta activa. Una meta común motiva a que se ayuden entre ellas.
          </p>
        )
      )}

      {anteriores.length > 0 && (
        <div className="flex flex-col gap-2">
          <h2 className="m-0 text-titulo">Anteriores</h2>
          <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {anteriores.map((m) => (
              <li key={m.id} className="flex items-center justify-between gap-3 rounded-tarjeta-interna bg-superficie p-3">
                <span className="text-cuerpo">{m.titulo}</span>
                <span className="text-etiqueta text-tinta-600">
                  {ESTADO[m.estado]} · {m.progreso_puntos}/{m.objetivo_puntos}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </section>
  );
}

export function Metas() {
  const hoy = fechaLocal();
  const metas = useMetas();
  const perfiles = usePerfilesAdmin();
  const guardar = useGuardarMeta();
  const [editando, setEditando] = useState<Meta | 'nueva' | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);

  if (metas.isPending || perfiles.isPending) return <Cargando />;
  if (metas.isError || perfiles.isError) return <p className="text-cuerpo text-indigo-700">No se pudieron cargar las metas. Intenta de nuevo.</p>;

  const d = guardar.error ? detalleError(guardar.error) : null;
  const alFallar = (e: unknown) =>
    setAviso(detalleError(e).codigo === 'ESTADO_INVALIDO' ? 'Ya hay una meta activa; termínala o cancélala antes de crear otra.' : detalleError(e).campos ? null : 'No se pudo guardar. Intenta otra vez.');

  return (
    <MetasVista
      metas={metas.data}
      perfiles={perfiles.data.filter((p) => p.activo)}
      hoy={hoy}
      editando={editando}
      guardando={guardar.isPending}
      aviso={aviso}
      erroresServidor={d?.campos ?? {}}
      onEditar={(m) => {
        guardar.reset();
        setAviso(null);
        setEditando(m);
      }}
      onGuardar={(id, datos) => guardar.mutate({ id, datos }, { onSuccess: () => setEditando(null), onError: alFallar })}
      onCancelarMeta={(m) => {
        if (!window.confirm(`¿Cancelar la meta "${m.titulo}"? Los puntos de cada hija no se pierden.`)) return;
        const { titulo, premio, objetivo_puntos, fecha_inicio, fecha_limite } = m;
        guardar.mutate({ id: m.id, datos: { titulo, premio, objetivo_puntos, fecha_inicio, fecha_limite, estado: 'cancelada' } }, { onError: alFallar });
      }}
    />
  );
}
