import { Check, CheckCheck, Inbox, Undo2 } from 'lucide-react';
import { useState } from 'react';
import type { InstanciaRevision } from '../../api/ApiClient.ts';
import { api } from '../../api/HttpApiClient.ts';
import { useAprobaciones, useAprobarLote, useRevisar } from '../../hooks/useAprobaciones.ts';
import { detalleError } from '../../lib/errores.ts';
import { fechaCorta } from '../../lib/fechas.ts';
import { Boton } from '../../ui/Boton.tsx';
import { SkeletonLista } from '../../ui/Cargando.tsx';
import { EstadoError } from '../../ui/EstadoError.tsx';
import { LightboxFoto } from '../../ui/LightboxFoto.tsx';
import { Toast } from '../../ui/Toast.tsx';
import { DevolverForm } from './DevolverForm.tsx';
import { ParentApprovalItem } from './ParentApprovalItem.tsx';
import { resumirResultados } from './resumirResultados.ts';

interface VistaProps {
  items: InstanciaRevision[];
  seleccion: number[];
  devolviendo: number | null;
  ocupado: boolean;
  aviso: string | null;
  onCerrarAviso?: () => void;
  urlFoto: (evidenciaId: number) => string;
  onSeleccionar: (id: number, valor: boolean) => void;
  onSeleccionarTodas: (valor: boolean) => void;
  onAprobar: (id: number) => void;
  onAprobarSeleccion: () => void;
  onAbrirDevolver: (id: number | null) => void;
  onDevolver: (id: number, mensaje: string) => void;
}

/** Bandeja de aprobación (RF-07). */
export function AprobacionesVista({ items, seleccion, devolviendo, ocupado, aviso, onCerrarAviso, urlFoto, onSeleccionar, onSeleccionarTodas, onAprobar, onAprobarSeleccion, onAbrirDevolver, onDevolver }: VistaProps) {
  const [filtroPerfil, setFiltroPerfil] = useState<number | null>(null);
  const [fotoActiva, setFotoActiva] = useState<{ item: InstanciaRevision; url: string } | null>(null);

  const perfilesUnicos = Array.from(new Map(items.map((i) => [i.perfil.id, i.perfil])).values());
  const visibles = filtroPerfil === null ? items : items.filter((i) => i.perfil.id === filtroPerfil);
  const todas = items.length > 0 && seleccion.length === items.length;
  const claseFiltro = (activo: boolean) =>
    `min-h-tactil rounded-boton px-4 text-etiqueta transition-colors ${activo ? 'bg-indigo-600 text-blanco' : 'bg-superficie text-tinta-900 hover:bg-lavanda-100 hover:text-indigo-700'}`;

  return (
    <section className="flex flex-col gap-4">
      <div className={`flex flex-wrap items-center justify-between gap-3 ${seleccion.length > 0 ? 'sticky top-16 z-10 rounded-tarjeta bg-fondo/95 py-2 backdrop-blur-xs' : ''}`}>
        <h1 className="m-0 text-display">Por aprobar</h1>
        {items.length > 0 && (
          <Boton icono={<CheckCheck className="size-5" aria-hidden="true" />} disabled={seleccion.length === 0 || ocupado} onClick={onAprobarSeleccion}>
            Aprobar {seleccion.length > 0 ? seleccion.length : ''} seleccionadas
          </Boton>
        )}
      </div>

      <Toast mensaje={aviso} tono="menta" onCerrar={onCerrarAviso} />

      {items.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-tarjeta bg-superficie p-10 text-center">
          <Inbox className="size-10 text-tinta-600" aria-hidden="true" />
          <p className="m-0 text-cuerpo text-tinta-600">No hay nada por aprobar. ¡Todo al día!</p>
        </div>
      ) : (
        <>
          <div className="flex flex-wrap items-center gap-2">
            <label className="flex min-h-tactil cursor-pointer items-center gap-2 text-etiqueta text-tinta-600">
              <input type="checkbox" className="size-5 accent-[var(--tf-indigo-600)]" checked={todas} onChange={(e) => onSeleccionarTodas(e.target.checked)} />
              Seleccionar todas ({items.length})
            </label>
            {perfilesUnicos.length > 1 && (
              <div className="ml-auto flex flex-wrap gap-2">
                <button type="button" className={claseFiltro(filtroPerfil === null)} onClick={() => setFiltroPerfil(null)}>
                  Todas
                </button>
                {perfilesUnicos.map((p) => (
                  <button key={p.id} type="button" className={claseFiltro(filtroPerfil === p.id)} onClick={() => setFiltroPerfil(p.id)}>
                    {p.apodo}
                  </button>
                ))}
              </div>
            )}
          </div>
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {visibles.map((item) => (
              <li key={item.id} className="flex flex-col gap-2">
                <ParentApprovalItem
                  item={item}
                  urlFoto={item.evidencia_id !== null ? urlFoto(item.evidencia_id) : null}
                  seleccionada={seleccion.includes(item.id)}
                  onSeleccionar={onSeleccionar}
                  onVerFoto={(it, url) => setFotoActiva({ item: it, url })}
                  onAprobar={onAprobar}
                  onDevolver={(id) => onAbrirDevolver(devolviendo === id ? null : id)}
                />
                {devolviendo === item.id && (
                  <DevolverForm item={item} enviando={ocupado} onCancelar={() => onAbrirDevolver(null)} onDevolver={(m) => onDevolver(item.id, m)} />
                )}
              </li>
            ))}
          </ul>
        </>
      )}

      <LightboxFoto
        abierto={fotoActiva !== null}
        titulo={fotoActiva?.item.titulo ?? ''}
        subtitulo={fotoActiva ? `${fotoActiva.item.perfil.apodo} · ${fechaCorta(fotoActiva.item.fecha)} · +${fotoActiva.item.puntos}` : undefined}
        src={fotoActiva?.url ?? ''}
        alt={fotoActiva ? `Foto de ${fotoActiva.item.titulo}` : ''}
        onCerrar={() => setFotoActiva(null)}
        acciones={
          fotoActiva && (
            <>
              <Boton
                variante="secundario"
                tamano="compacto"
                disabled={ocupado}
                icono={<Undo2 className="size-4" aria-hidden="true" />}
                onClick={() => {
                  const id = fotoActiva.item.id;
                  setFotoActiva(null);
                  onAbrirDevolver(id);
                }}
              >
                Devolver
              </Boton>
              <Boton
                variante="primario"
                tamano="compacto"
                disabled={ocupado}
                icono={<Check className="size-4" aria-hidden="true" />}
                onClick={() => {
                  const id = fotoActiva.item.id;
                  setFotoActiva(null);
                  onAprobar(id);
                }}
              >
                Aprobar
              </Boton>
            </>
          )
        }
      />
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

  if (bandeja.isPending) return <SkeletonLista filas={3} texto="Cargando bandeja de aprobaciones…" />;
  if (bandeja.isError) return <EstadoError mensaje="No se pudo cargar la bandeja. Intenta de nuevo." onReintentar={() => void bandeja.refetch()} />;

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
      onCerrarAviso={() => setAviso(null)}
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
