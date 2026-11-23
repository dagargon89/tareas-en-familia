import type { ReactNode } from 'react';

type Tono = 'ambar' | 'lavanda' | 'rosa' | 'menta';

const TONOS: Record<Tono, string> = {
  ambar: 'bg-ambar-200 text-tinta-900',
  lavanda: 'bg-lavanda-100 text-indigo-700',
  rosa: 'bg-rosa-200 text-tinta-900',
  menta: 'bg-menta-200 text-tinta-900',
};

/** Píldora pequeña; base de PointsChip y StreakBadge (doc 08 §4). */
export function Chip({ tono = 'ambar', children }: { tono?: Tono; children: ReactNode }) {
  return (
    <span className={`inline-flex items-center gap-1 whitespace-nowrap rounded-boton px-3 py-1 text-etiqueta ${TONOS[tono]}`}>
      {children}
    </span>
  );
}
