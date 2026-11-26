import { LoaderCircle } from 'lucide-react';

export function Cargando({ texto = 'Cargando…' }: { texto?: string }) {
  return (
    <div role="status" className="flex min-h-[50dvh] items-center justify-center gap-3 text-cuerpo text-tinta-600">
      <LoaderCircle className="size-6 animate-spin motion-reduce:animate-none" aria-hidden="true" />
      {texto}
    </div>
  );
}

/** Skeleton contextual para listas de tarjetas (evita saltos bruscos de layout). */
export function SkeletonLista({ filas = 3, texto = 'Cargando…' }: { filas?: number; texto?: string }) {
  return (
    <div role="status" aria-label={texto} className="flex flex-col gap-4">
      <div className="h-10 w-48 animate-pulse rounded-tarjeta-interna bg-superficie motion-reduce:animate-none" />
      {Array.from({ length: filas }, (_, i) => (
        <div key={i} className="flex items-center gap-4 rounded-tarjeta bg-superficie p-5">
          <div className="size-12 shrink-0 animate-pulse rounded-icono bg-tarjeta motion-reduce:animate-none" />
          <div className="flex flex-1 flex-col gap-2">
            <div className="h-5 w-2/3 animate-pulse rounded-boton bg-tarjeta motion-reduce:animate-none" />
            <div className="h-4 w-1/3 animate-pulse rounded-boton bg-tarjeta motion-reduce:animate-none" />
          </div>
        </div>
      ))}
      <span className="sr-only">{texto}</span>
    </div>
  );
}

/** Skeleton contextual para rejillas de 2 columnas (premios, panel). */
export function SkeletonRejilla({ tarjetas = 4, texto = 'Cargando…' }: { tarjetas?: number; texto?: string }) {
  return (
    <div role="status" aria-label={texto} className="flex flex-col gap-5">
      <div className="h-10 w-40 animate-pulse rounded-tarjeta-interna bg-superficie motion-reduce:animate-none" />
      <div className="grid gap-4 sm:grid-cols-2">
        {Array.from({ length: tarjetas }, (_, i) => (
          <div key={i} className="flex flex-col gap-4 rounded-tarjeta bg-superficie p-6">
            <div className="flex items-center gap-4">
              <div className="size-14 shrink-0 animate-pulse rounded-icono bg-tarjeta motion-reduce:animate-none" />
              <div className="flex flex-1 flex-col gap-2">
                <div className="h-5 w-3/4 animate-pulse rounded-boton bg-tarjeta motion-reduce:animate-none" />
                <div className="h-4 w-1/3 animate-pulse rounded-boton bg-tarjeta motion-reduce:animate-none" />
              </div>
            </div>
            <div className="h-11 w-full animate-pulse rounded-boton bg-tarjeta motion-reduce:animate-none" />
          </div>
        ))}
      </div>
      <span className="sr-only">{texto}</span>
    </div>
  );
}
