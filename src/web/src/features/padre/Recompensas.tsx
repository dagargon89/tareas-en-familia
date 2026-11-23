import { Check, Gift, PackageCheck, Pencil, Plus, X } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import type { Canje, PerfilPublico, Recompensa, RecompensaEntrada } from '../../api/ApiClient.ts';
import { useCanjesAdmin, useGuardarRecompensa, useRecompensasAdmin, useRevisarCanje } from '../../hooks/usePremios.ts';
import { usePerfilesAdmin } from '../../hooks/usePerfilesAdmin.ts';
import { FONDO_COLOR } from '../../lib/avatares.ts';
import { detalleError } from '../../lib/errores.ts';
import { fechaCorta } from '../../lib/fechas.ts';
import { ICONOS, iconoDe } from '../../lib/iconos.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';
import { Cargando } from '../../ui/Cargando.tsx';
import { COSTO_MAXIMO, validarRecompensa } from './validarJuego.ts';

// ===== Formulario de recompensa =====

export function FormRecompensa({ recompensa, enviando, onGuardar, onCancelar }: { recompensa: Recompensa | null; enviando: boolean; onGuardar: (r: RecompensaEntrada) => void; onCancelar: () => void }) {
  const [r, setR] = useState<RecompensaEntrada>({ titulo: recompensa?.titulo ?? '', icono: recompensa?.icono ?? 'regalo', costo: recompensa?.costo ?? 50, activa: recompensa?.activa ?? true });
  const [tocado, setTocado] = useState(false);
  const errores = validarRecompensa(r);
  const ver = (k: keyof RecompensaEntrada) => (tocado ? errores[k] : undefined);

  function enviar(e: FormEvent) {
    e.preventDefault();
    setTocado(true);
    if (Object.keys(errores).length === 0) onGuardar({ ...r, titulo: r.titulo.trim() });
  }

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-5 rounded-tarjeta bg-superficie p-5">
      <Campo etiqueta="Premio" maxLength={60} value={r.titulo} onChange={(e) => setR({ ...r, titulo: e.target.value })} error={ver('titulo')} />
      <Campo etiqueta="Costo en puntos" type="number" min={1} max={COSTO_MAXIMO} value={Number.isNaN(r.costo) ? '' : r.costo} onChange={(e) => setR({ ...r, costo: e.target.valueAsNumber })} error={ver('costo')} />
      <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
        <legend className="mb-2 text-etiqueta">Ícono</legend>
        <div className="grid grid-cols-8 gap-2 sm:grid-cols-12">
          {Object.entries(ICONOS).map(([clave, I]) => (
            <button key={clave} type="button" aria-label={clave} aria-pressed={r.icono === clave} onClick={() => setR({ ...r, icono: clave })} className={`flex aspect-square items-center justify-center rounded-icono ${r.icono === clave ? FONDO_COLOR.indigo : 'bg-blanco text-tinta-900'}`}>
              <I className="size-1/2" aria-hidden="true" />
            </button>
          ))}
        </div>
      </fieldset>
      <label className="flex min-h-tactil items-center gap-3 text-cuerpo">
        <input type="checkbox" className="size-6 accent-[var(--tf-indigo-600)]" checked={r.activa} onChange={(e) => setR({ ...r, activa: e.target.checked })} />
        Visible para las hijas
      </label>
      <div className="flex justify-end gap-2">
        <Boton variante="suave" onClick={onCancelar}>
          Cancelar
        </Boton>
        <Boton type="submit" disabled={enviando}>
          {enviando ? 'Guardando…' : recompensa ? 'Guardar' : 'Agregar premio'}
        </Boton>
      </div>
    </form>
  );
}

// ===== Vista =====

interface VistaProps {
  canjes: Canje[];
  recompensas: Recompensa[];
  perfiles: PerfilPublico[];
  ocupado: boolean;
  aviso: string | null;
  editando: Recompensa | 'nueva' | null;
  guardando: boolean;
  onRevisar: (c: Canje, decision: 'aprobado' | 'rechazado' | 'entregado') => void;
  onEditar: (r: Recompensa | 'nueva' | null) => void;
  onGuardar: (id: number | null, datos: RecompensaEntrada) => void;
}

/** Premios en modo padre (RF-09): primero lo que espera respuesta, luego el catálogo. */
export function RecompensasVista({ canjes, recompensas, perfiles, ocupado, aviso, editando, guardando, onRevisar, onEditar, onGuardar }: VistaProps) {
  const apodo = (id: number) => perfiles.find((p) => p.id === id)?.apodo ?? '—';
  const porRevisar = canjes.filter((c) => c.estado === 'solicitado');
  const porEntregar = canjes.filter((c) => c.estado === 'aprobado');
  const accion = 'inline-flex min-h-tactil items-center gap-1 rounded-boton px-4 text-etiqueta';

  const fila = (c: Canje, botones: ReactNode) => {
    const I = iconoDe(c.recompensa.icono);
    return (
      <li key={c.id} className="flex flex-wrap items-center gap-3 rounded-tarjeta bg-superficie p-4">
        <span className="flex size-11 items-center justify-center rounded-icono bg-blanco text-indigo-600">
          <I className="size-6" aria-hidden="true" />
        </span>
        <div className="flex min-w-0 flex-1 flex-col">
          <span className="text-titulo">{c.recompensa.titulo}</span>
          <span className="text-etiqueta text-tinta-600">
            {apodo(c.perfil_id)} · {c.costo} pts · {fechaCorta(c.created_at.slice(0, 10))}
          </span>
        </div>
        <div className="flex gap-2">{botones}</div>
      </li>
    );
  };

  return (
    <section className="flex flex-col gap-6">
      <h1 className="m-0 text-display">Premios</h1>
      <p aria-live="polite" className="m-0 empty:hidden rounded-tarjeta-interna bg-menta-200 p-3 text-cuerpo">
        {aviso}
      </p>

      <div className="flex flex-col gap-3">
        <h2 className="m-0 text-titulo">Por revisar ({porRevisar.length})</h2>
        {porRevisar.length === 0 ? (
          <p className="m-0 text-cuerpo text-tinta-600">Nadie ha pedido premios.</p>
        ) : (
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {porRevisar.map((c) =>
              fila(
                c,
                <>
                  <button type="button" disabled={ocupado} onClick={() => onRevisar(c, 'rechazado')} className={`${accion} bg-lavanda-100 text-indigo-700`}>
                    <X className="size-4" aria-hidden="true" /> Ahora no
                  </button>
                  <button type="button" disabled={ocupado} onClick={() => onRevisar(c, 'aprobado')} className={`${accion} bg-indigo-600 text-blanco`}>
                    <Check className="size-4" aria-hidden="true" /> Aprobar
                  </button>
                </>,
              ),
            )}
          </ul>
        )}
      </div>

      {porEntregar.length > 0 && (
        <div className="flex flex-col gap-3">
          <h2 className="m-0 text-titulo">Por entregar ({porEntregar.length})</h2>
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {porEntregar.map((c) =>
              fila(
                c,
                <button type="button" disabled={ocupado} onClick={() => onRevisar(c, 'entregado')} className={`${accion} bg-menta-200 text-tinta-900`}>
                  <PackageCheck className="size-4" aria-hidden="true" /> Ya lo entregué
                </button>,
              ),
            )}
          </ul>
        </div>
      )}

      <div className="flex flex-col gap-3">
        <div className="flex items-center justify-between gap-3">
          <h2 className="m-0 text-titulo">Catálogo</h2>
          {editando === null && (
            <Boton icono={<Plus className="size-5" aria-hidden="true" />} onClick={() => onEditar('nueva')}>
              Nuevo
            </Boton>
          )}
        </div>
        {editando !== null && (
          <FormRecompensa
            key={editando === 'nueva' ? 'nueva' : editando.id}
            recompensa={editando === 'nueva' ? null : editando}
            enviando={guardando}
            onCancelar={() => onEditar(null)}
            onGuardar={(datos) => onGuardar(editando === 'nueva' ? null : editando.id, datos)}
          />
        )}
        {recompensas.length === 0 ? (
          <p className="m-0 flex items-center gap-2 rounded-tarjeta bg-superficie p-6 text-cuerpo text-tinta-600">
            <Gift className="size-5" aria-hidden="true" /> Agrega de 5 a 10 premios que hayan acordado en familia.
          </p>
        ) : (
          <ul className="m-0 grid list-none gap-3 p-0 sm:grid-cols-2">
            {recompensas.map((r) => {
              const I = iconoDe(r.icono);
              return (
                <li key={r.id} className={`flex items-center gap-3 rounded-tarjeta bg-superficie p-4 ${r.activa ? '' : 'opacity-60'}`}>
                  <span className="flex size-11 items-center justify-center rounded-icono bg-blanco text-indigo-600">
                    <I className="size-6" aria-hidden="true" />
                  </span>
                  <div className="flex min-w-0 flex-1 flex-col">
                    <span className="text-titulo">{r.titulo}</span>
                    <span className="text-etiqueta text-tinta-600">
                      {r.costo} pts{r.activa ? '' : ' · oculto'}
                    </span>
                  </div>
                  <button type="button" onClick={() => onEditar(r)} className={`${accion} text-indigo-700 hover:bg-lavanda-100`} aria-label={`Editar ${r.titulo}`}>
                    <Pencil className="size-4" aria-hidden="true" />
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </section>
  );
}

const TEXTO_REVISION = { aprobado: 'Aprobado: se descontaron los puntos.', rechazado: 'Listo: sus puntos quedaron libres otra vez.', entregado: '¡Entregado!' } as const;

export function Recompensas() {
  const canjes = useCanjesAdmin();
  const recompensas = useRecompensasAdmin();
  const perfiles = usePerfilesAdmin();
  const revisar = useRevisarCanje();
  const guardar = useGuardarRecompensa();
  const [aviso, setAviso] = useState<string | null>(null);
  const [editando, setEditando] = useState<Recompensa | 'nueva' | null>(null);

  if (canjes.isPending || recompensas.isPending || perfiles.isPending) return <Cargando />;
  if (canjes.isError || recompensas.isError || perfiles.isError) return <p className="text-cuerpo text-indigo-700">No se pudieron cargar los premios. Intenta de nuevo.</p>;

  return (
    <RecompensasVista
      canjes={canjes.data}
      recompensas={recompensas.data}
      perfiles={perfiles.data}
      ocupado={revisar.isPending}
      aviso={aviso}
      editando={editando}
      guardando={guardar.isPending}
      onRevisar={(c, decision) =>
        revisar.mutate(
          { id: c.id, decision },
          {
            onSuccess: () => setAviso(TEXTO_REVISION[decision]),
            onError: (e) => setAviso(detalleError(e).codigo === 'ESTADO_INVALIDO' ? 'Ese canje ya se había revisado; actualicé la lista.' : 'No se pudo guardar. Intenta otra vez.'),
          },
        )
      }
      onEditar={setEditando}
      onGuardar={(id, datos) => guardar.mutate({ id, datos }, { onSuccess: () => setEditando(null) })}
    />
  );
}
