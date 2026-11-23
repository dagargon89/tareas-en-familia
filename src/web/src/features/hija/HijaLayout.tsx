import { CalendarDays, Gift, ListChecks, Medal, Sun } from 'lucide-react';
import { NavLink, Outlet, useNavigate } from 'react-router';
import type { NivelUx, PerfilPublico } from '../../api/ApiClient.ts';
import { useSalirPerfil, useSesion } from '../../hooks/useSesion.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { FormasVivas } from '../../ui/FormasVivas.tsx';

/** Navegación por nivel (doc 08 §3): 3 botones, 4 pestañas o 5 pestañas. */
function seccionesDe(nivel: NivelUx) {
  const v = VOCABULARIO[nivel];
  const base = [
    { a: '/hoy', texto: nivel === 'pro' ? 'Hoy' : 'Mi día', Icono: Sun },
    { a: '/premios', texto: 'Premios', Icono: Gift },
    { a: '/logros', texto: nivel === 'exploradoras' ? 'Mis insignias' : 'Logros', Icono: Medal },
  ];
  if (nivel === 'exploradoras') return base;
  const conCalendario = [...base, { a: '/calendario', texto: 'Semana', Icono: CalendarDays }];
  if (nivel === 'aventureras') return conCalendario;
  return [...conCalendario, { a: '/mis-tareas', texto: `Mis ${v.tareas.toLowerCase()}`, Icono: ListChecks }];
}

export function HijaLayoutVista({ perfil, onCambiar }: { perfil: PerfilPublico; onCambiar: () => void }) {
  const Avatar = avatarDe(perfil.avatar);
  const secciones = seccionesDe(perfil.nivel_ux);
  return (
    <div className="relative flex min-h-dvh flex-col">
      <FormasVivas />
      <header className="flex items-center gap-3 px-4 pt-4 sm:px-8">
        <span className={`flex size-12 items-center justify-center rounded-full ${FONDO_COLOR[perfil.color]}`}>
          <Avatar className="size-6" aria-hidden="true" />
        </span>
        <span className="text-titulo">{perfil.apodo}</span>
        <button type="button" onClick={onCambiar} className="ml-auto min-h-11 rounded-boton px-4 text-etiqueta text-indigo-700 hover:bg-lavanda-100">
          Cambiar de perfil
        </button>
      </header>
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 pt-4 pb-32 sm:px-8">
        <Outlet />
      </main>
      <nav aria-label="Secciones" className="fixed inset-x-0 bottom-0 border-t border-superficie bg-fondo px-2 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
        <ul className="mx-auto flex max-w-3xl justify-around gap-1">
          {secciones.map(({ a, texto, Icono }) => (
            <li key={a} className="flex-1">
              <NavLink
                to={a}
                className={({ isActive }) =>
                  `flex min-h-tactil flex-col items-center justify-center gap-1 rounded-tarjeta-interna px-2 py-1 text-etiqueta ${isActive ? 'bg-lavanda-100 text-indigo-700' : 'text-tinta-600'}`
                }
              >
                <Icono style={{ width: 'calc(var(--tf-icono) * 0.75)', height: 'calc(var(--tf-icono) * 0.75)' }} aria-hidden="true" />
                {texto}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>
    </div>
  );
}

/** Marco de las pantallas de la hija: perfil arriba, navegación del nivel abajo. */
export function HijaLayout() {
  const navegar = useNavigate();
  const { data } = useSesion();
  const salir = useSalirPerfil();
  if (!data?.perfil_activo) return null;
  return <HijaLayoutVista perfil={data.perfil_activo} onCambiar={() => salir.mutate(undefined, { onSuccess: () => navegar('/', { replace: true }) })} />;
}
