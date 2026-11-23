import { CheckCheck, Inbox } from 'lucide-react';
import { useState } from 'react';
import type { InstanciaRevision } from '../../api/ApiClient.ts';
import { api } from '../../api/HttpApiClient.ts';
import { useAprobaciones, useAprobarLote, useRevisar } from '../../hooks/useAprobaciones.ts';
import { detalleError } from '../../lib/errores.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Cargando } from '../../ui/Cargando.tsx';
import { DevolverForm } from './DevolverForm.tsx';
import { ParentApprovalItem } from './ParentApprovalItem.tsx';
import { resumirResultados } from './resumirResultados.ts';

interface VistaProps {
  items: InstanciaRevision[];
  seleccion: number[];
  devolviendo: number | null;
  ocupado: boolean;
  aviso: string | null;
  urlFoto: (evidenciaId: number) => string;
  onSeleccionar: (id: number, valor: boolean) => void;
  onSeleccionarTodas: (valor: boolean) => void;
  onAprobar: (id: number) => void;
  onAprobarSeleccion: () => void;
  onAbrirDevolver: (id: number | null) => void;
  onDevolver: (id: number, mensaje: string) => void;
}

/** Bandeja de aprobación (RF-07). */
export function AprobacionesVista({ items, seleccion, devolviendo, ocupado, aviso, urlFoto, onSeleccionar, onSeleccionarTodas, onAprobar, onAprobarSeleccion, onAbrirDevolver, onDevolver }: VistaProps) {
  const todas = items.length > 0 && seleccion.length === items.length;
  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="m-0 text-display">Por aprobar</h1>
        {items.length > 0 && (
          <Boton icono={<CheckCheck className="size-5" aria-hidden="true" />} disabled={seleccion.length === 0 || ocupado} onClick={onAprobarSeleccion}>
            Aprobar {seleccion.length > 0 ? seleccion.length : ''} seleccionadas
          </Boton>
        )}
      </div>

      <p aria-live="polite" className="m-0 empty:hidden rounded-tarjeta-interna bg-menta-200 p-3 text-cuerpo">
        {aviso}
      </p>

      {items.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-tarjeta bg-superficie p-10 text-center">
          <Inbox className="size-10 text-tinta-600" aria-hidden="true" />
          <p className="m-0 text-cuerpo text-tinta-600">No hay nada por aprobar. ¡Todo al día!</p>
        </div>
      ) : (
        <>
          <label className="flex items-center gap-2 text-etiqueta text-tinta-600">
            <input type="checkbox" className="size-5 accent-[var(--tf-indigo-600)]" checked={todas} onChange={(e) => onSeleccionarTodas(e.target.checked)} />
            Seleccionar todas ({items.length})
          </label>
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {items.map((item) => (
              <li key={item.id} className="flex flex-col gap-2">
                <ul className="m-0 list-none p-0">
                  <ParentApprovalItem
                    item={item}
                    urlFoto={item.evidencia_id !== null ? urlFoto(item.evidencia_id) : null}
                    seleccionada={seleccion.includes(item.id)}
                    onSeleccionar={onSeleccionar}
                    onAprobar={onAprobar}
                    onDevolver={(id) => onAbrirDevolver(devolviendo === id ? null : id)}
                  />
                </ul>
                {devolviendo === item.id && (
                  <DevolverForm item={item} enviando={ocupado} onCancelar={() => onAbrirDevolver(null)} onDevolver={(m) => onDevolver(item.id, m)} />
                )}
              </li>
            ))}
          </ul>
        </>
      )}
    </section>
  );
}

export function Aprobaciones() {
  const bandeja = useAprobaciones();
  const revisar = useRevisar();
  const lote = useAprobarLote();
  const [seleccion, setSeleccion] = useState<number[]>([]);
  const [devolviendo, setDevolviendo] = useState<number | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);

  if (bandeja.isPending) return <Cargando />;
  if (bandeja.isError) return <p className="text-cuerpo text-indigo-700">No se pudo cargar la bandeja. Intenta de nuevo.</p>;

  const items = bandeja.data;
  const alFallar = (e: unknown) =>
    setAviso(detalleError(e).codigo === 'ESTADO_INVALIDO' ? 'Esa tarea ya se había revisado; actualicé la lista.' : 'No se pudo guardar. Intenta otra vez.');
  const quitar = (ids: number[]) => setSeleccion((s) => s.filter((x) => !ids.includes(x)));

  return (
    <AprobacionesVista
      items={items}
      seleccion={seleccion.filter((id) => items.some((i) => i.id === id))}
      devolviendo={devolviendo}
      ocupado={revisar.isPending || lote.isPending}
      aviso={aviso}
      urlFoto={(id) => api.urlEvidencia(id)}
      onSeleccionar={(id, v) => setSeleccion((s) => (v ? [...s, id] : s.filter((x) => x !== id)))}
      onSeleccionarTodas={(v) => setSeleccion(v ? items.map((i) => i.id) : [])}
      onAprobar={(id) =>
        revisar.mutate(
          { id, decision: 'aprobada' },
          { onSuccess: (r) => { quitar([id]); setAviso(resumirResultados([r], items)); }, onError: alFallar },
        )
      }
      onAprobarSeleccion={() =>
        lote.mutate(seleccion, {
          onSuccess: (rs) => { quitar(seleccion); setAviso(resumirResultados(rs, items)); },
          onError: alFallar,
        })
      }
      onAbrirDevolver={setDevolviendo}
      onDevolver={(id, mensaje) =>
        revisar.mutate(
          { id, decision: 'devuelta', mensaje },
          { onSuccess: () => { quitar([id]); setDevolviendo(null); setAviso('Devuelta con tu mensaje.'); }, onError: alFallar },
        )
      }
    />
  );
}
