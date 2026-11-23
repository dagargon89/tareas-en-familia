import { LoaderCircle } from 'lucide-react';

export function Cargando({ texto = 'Cargando…' }: { texto?: string }) {
  return (
    <div role="status" className="flex min-h-[50dvh] items-center justify-center gap-3 text-cuerpo text-tinta-600">
      <LoaderCircle className="size-6 animate-spin" aria-hidden="true" />
      {texto}
    </div>
  );
}
