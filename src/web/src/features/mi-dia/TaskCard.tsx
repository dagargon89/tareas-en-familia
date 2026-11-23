import { CircleCheck, Hourglass, MessageCircleHeart } from 'lucide-react';
import { m } from 'motion/react';
import type { Instancia, NivelUx } from '../../api/ApiClient.ts';
import { fechaCorta } from '../../lib/fechas.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { Chip } from '../../ui/Chip.tsx';
import { Icono } from '../../ui/Icono.tsx';
import { DoneButton } from './DoneButton.tsx';

interface Props {
  instancia: Instancia;
  nivel: NivelUx;
  /** Si es false (día futuro) no se muestra el botón (RF-05). */
  puedeMarcar: boolean;
  cargando?: boolean;
  onListo?: (instancia: Instancia, origen: { x: number; y: number }) => void;
}

/** Tarjeta de una instancia en "Mi día" (RF-04, RF-05, RF-13, doc 08 §4). */
export function TaskCard({ instancia: i, nivel, puedeMarcar, cargando = false, onListo }: Props) {
  const v = VOCABULARIO[nivel];
  const marcable = puedeMarcar && (i.estado === 'pendiente' || i.estado === 'devuelta');
  const detalle = [i.materia, i.hora].filter(Boolean).join(' · ');

  return (
    <m.article
      layout
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0, scale: i.estado === 'hecha' ? [1, 1.04, 1] : 1 }}
      transition={{ duration: 0.3, ease: [0.34, 1.56, 0.64, 1] }}
      className={`flex flex-col gap-4 rounded-tarjeta p-5 ${i.estado === 'aprobada' ? 'bg-menta-200' : 'bg-superficie'}`}
    >
      <div className="flex items-center gap-4">
        <Icono clave={i.icono} color={i.color} />
        <div className="flex min-w-0 flex-1 flex-col">
          <h3 className="m-0 text-titulo">{i.titulo}</h3>
          {/* Exploradoras: solo título corto (doc 08 §3). */}
          {nivel !== 'exploradoras' && detalle && <span className="text-etiqueta text-tinta-600">{detalle}</span>}
          {i.tipo === 'escolar' && i.fecha_entrega && nivel !== 'exploradoras' && (
            <span className="text-etiqueta text-tinta-600">Entrega: {fechaCorta(i.fecha_entrega)}</span>
          )}
        </div>
        {i.puntos > 0 && (
          <Chip tono={i.estado === 'aprobada' ? 'menta' : 'ambar'}>
            +{i.puntos} {v.unidadPuntos}
          </Chip>
        )}
      </div>

      {i.estado === 'devuelta' && i.mensaje_devolucion && (
        <p className="m-0 flex items-start gap-2 rounded-tarjeta-interna bg-lavanda-100 p-4 text-cuerpo text-indigo-700">
          <MessageCircleHeart className="mt-1 size-5 shrink-0" aria-hidden="true" />
          {v.devuelta(i.mensaje_devolucion)}
        </p>
      )}

      {i.estado === 'hecha' && (
        <p className="m-0 flex items-center gap-2 text-cuerpo text-indigo-700">
          <Hourglass className="size-5" aria-hidden="true" /> {v.hecha}
        </p>
      )}

      {i.estado === 'aprobada' && (
        <p className="m-0 flex items-center gap-2 text-cuerpo">
          <CircleCheck className="size-5" aria-hidden="true" /> {v.aprobada}
        </p>
      )}

      {marcable && onListo && (
        <DoneButton
          etiqueta={v.listo}
          requiereFoto={i.requiere_foto}
          cargando={cargando}
          onListo={(origen) => onListo(i, origen)}
        />
      )}
    </m.article>
  );
}
