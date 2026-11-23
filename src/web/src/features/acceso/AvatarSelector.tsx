import { Lock } from 'lucide-react';
import type { PerfilPublico } from '../../api/ApiClient.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { minutosRestantes } from '../../lib/fechas.ts';

interface Props {
  perfiles: PerfilPublico[];
  onElegir: (perfil: PerfilPublico) => void;
  ahora?: Date;
}

/** Rejilla de avatares grandes con el apodo debajo (RF-01, doc 08 §4). */
export function AvatarSelector({ perfiles, onElegir, ahora = new Date() }: Props) {
  return (
    <ul className="grid grid-cols-2 gap-6 sm:grid-cols-3">
      {perfiles.map((p) => {
        const Avatar = avatarDe(p.avatar);
        const minutos = p.bloqueado_hasta ? minutosRestantes(p.bloqueado_hasta, ahora) : 0;
        const bloqueado = minutos > 0;
        return (
          <li key={p.id}>
            <button
              type="button"
              onClick={() => onElegir(p)}
              disabled={bloqueado}
              aria-label={bloqueado ? `${p.apodo}, descansando ${minutos} minutos` : p.apodo}
              className="group flex w-full flex-col items-center gap-3 rounded-tarjeta p-4 transition duration-150 ease-rebote hover:bg-superficie active:scale-95 disabled:opacity-60"
            >
              <span className={`relative flex aspect-square w-full max-w-40 items-center justify-center rounded-full ${FONDO_COLOR[p.color]}`}>
                <Avatar className="h-1/2 w-1/2" strokeWidth={1.75} aria-hidden="true" />
                {bloqueado && (
                  <span className="absolute -right-1 -bottom-1 flex items-center gap-1 rounded-boton bg-lavanda-100 px-3 py-1 text-etiqueta text-indigo-700">
                    <Lock className="size-4" aria-hidden="true" /> {minutos} min
                  </span>
                )}
              </span>
              <span className="text-titulo">{p.apodo}</span>
            </button>
          </li>
        );
      })}
    </ul>
  );
}
