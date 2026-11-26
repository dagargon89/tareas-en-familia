import { Check, Undo2 } from 'lucide-react';
import type { InstanciaRevision } from '../../api/ApiClient.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { fechaCorta } from '../../lib/fechas.ts';
import { Icono } from '../../ui/Icono.tsx';

interface Props {
  item: InstanciaRevision;
  urlFoto?: string | null;
  seleccionada?: boolean;
  onSeleccionar?: (id: number, valor: boolean) => void;
  onVerFoto?: (item: InstanciaRevision, url: string) => void;
  onAprobar?: (id: number) => void;
  onDevolver?: (id: number) => void;
}

/** Fila de la bandeja de aprobación (RF-07, doc 08 §4). */
export function ParentApprovalItem({ item, urlFoto, seleccionada = false, onSeleccionar, onVerFoto, onAprobar, onDevolver }: Props) {
  const Avatar = avatarDe(item.perfil.avatar);
  return (
    <div className="flex flex-wrap items-center gap-4 rounded-tarjeta bg-superficie p-4">
      {onSeleccionar && (
        <input
          type="checkbox"
          className="size-6 accent-[var(--tf-indigo-600)]"
          checked={seleccionada}
          onChange={(e) => onSeleccionar(item.id, e.target.checked)}
          aria-label={`Seleccionar ${item.titulo} de ${item.perfil.apodo}`}
        />
      )}
      <span className={`flex size-11 shrink-0 items-center justify-center rounded-full ${FONDO_COLOR[item.perfil.color]}`} title={item.perfil.apodo}>
        <Avatar className="size-6" aria-hidden="true" />
      </span>
      <Icono clave={item.icono} color={item.color} />
      <div className="flex min-w-0 flex-1 flex-col">
        <span className="text-titulo">{item.titulo}</span>
        <span className="text-etiqueta text-tinta-600">
          {item.perfil.apodo} · {fechaCorta(item.fecha)} · +{item.puntos}
        </span>
      </div>
      {urlFoto && (
        onVerFoto ? (
          <button
            type="button"
            onClick={() => onVerFoto(item, urlFoto)}
            className="shrink-0 overflow-hidden rounded-icono transition hover:opacity-85"
            aria-label={`Ampliar foto de ${item.titulo}`}
          >
            <img src={urlFoto} alt={`Foto de ${item.titulo}`} className="size-14 rounded-icono object-cover" />
          </button>
        ) : (
          <a href={urlFoto} target="_blank" rel="noreferrer" className="shrink-0">
            <img src={urlFoto} alt={`Foto de ${item.titulo}`} className="size-14 rounded-icono object-cover" />
          </a>
        )
      )}
      <div className="flex gap-2">
        <button
          type="button"
          onClick={() => onDevolver?.(item.id)}
          className="inline-flex min-h-tactil items-center gap-1 rounded-boton bg-lavanda-100 px-4 text-etiqueta text-indigo-700 transition hover:brightness-95 active:scale-95 motion-reduce:active:scale-100"
        >
          <Undo2 className="size-4" aria-hidden="true" /> Devolver
        </button>
        <button
          type="button"
          onClick={() => onAprobar?.(item.id)}
          className="inline-flex min-h-tactil items-center gap-1 rounded-boton bg-indigo-600 px-4 text-etiqueta text-blanco transition hover:bg-indigo-hover active:scale-95 motion-reduce:active:scale-100"
        >
          <Check className="size-4" aria-hidden="true" /> Aprobar
        </button>
      </div>
    </div>
  );
}
