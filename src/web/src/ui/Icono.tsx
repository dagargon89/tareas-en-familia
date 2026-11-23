import type { ColorToken } from '../api/ApiClient.ts';
import { FONDO_COLOR } from '../lib/avatares.ts';
import { iconoDe } from '../lib/iconos.ts';

/** Ícono de tarea dentro de su forma de color de categoría; el tamaño sigue al nivel. */
export function Icono({ clave, color }: { clave: string; color: ColorToken }) {
  const Componente = iconoDe(clave);
  return (
    <span
      aria-hidden="true"
      className={`flex shrink-0 items-center justify-center rounded-icono ${FONDO_COLOR[color]}`}
      style={{ width: 'calc(var(--tf-icono) + 16px)', height: 'calc(var(--tf-icono) + 16px)' }}
    >
      <Componente style={{ width: 'calc(var(--tf-icono) * 0.75)', height: 'calc(var(--tf-icono) * 0.75)' }} strokeWidth={2} />
    </span>
  );
}
