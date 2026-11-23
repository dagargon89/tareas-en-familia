import { CalendarDays, ChartColumn, Gift, Inbox, ListChecks, LogOut, Settings, Target, Users } from 'lucide-react';
import { useEffect } from 'react';
import { NavLink, Outlet, useMatch, useNavigate } from 'react-router';
import { useLogout, useSalirModoPadre, useSesion } from '../../hooks/useSesion.ts';
import { aplicarNivel, aplicarTema } from '../../niveles/nivel.ts';

const SECCIONES = [
  { a: '/padre', texto: 'Panel', Icono: ChartColumn, fin: true },
  { a: '/padre/aprobaciones', texto: 'Aprobar', Icono: Inbox },
  { a: '/padre/tareas', texto: 'Tareas', Icono: ListChecks },
  { a: '/padre/semana', texto: 'Semana', Icono: CalendarDays },
  { a: '/padre/perfiles', texto: 'Perfiles', Icono: Users },
  { a: '/padre/premios', texto: 'Premios', Icono: Gift },
  { a: '/padre/metas', texto: 'Metas', Icono: Target },
  { a: '/padre/ajustes', texto: 'Ajustes', Icono: Settings },
];

/**
 * Marco del modo padre. Usa la densidad del nivel Pro (adultos). Refresca la sesión cada
 * minuto para reflejar la renovación deslizante de 10 min que hace el servidor (RF-02).
 */
export function PadreLayout() {
  const navegar = useNavigate();
  const salir = useSalirModoPadre();
  const logout = useLogout();
  // La semana usa el ancho del escritorio para su tablero; las demás pantallas, una columna cómoda.
  const amplia = useMatch('/padre/semana') !== null;

  useEffect(() => {
    aplicarNivel('pro');
    aplicarTema('claro');
  }, []);
  useSesion(60_000);

  return (
    <div className="flex min-h-dvh flex-col">
      <header className="sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b border-superficie bg-fondo px-4 py-2">
        <span className="text-titulo">Modo padre</span>
        <nav aria-label="Secciones" className="order-last -mx-4 flex w-[calc(100%+2rem)] gap-1 overflow-x-auto px-4 sm:order-none sm:mx-0 sm:w-auto sm:flex-1 sm:px-0">
          {SECCIONES.map(({ a, texto, Icono, fin }) => (
            <NavLink
              key={a}
              to={a}
              end={fin}
              className={({ isActive }) =>
                `inline-flex min-h-tactil shrink-0 items-center gap-2 rounded-boton px-4 text-etiqueta ${isActive ? 'bg-indigo-600 text-blanco' : 'text-tinta-600 hover:bg-superficie'}`
              }
            >
              <Icono className="size-4" aria-hidden="true" /> {texto}
            </NavLink>
          ))}
        </nav>
        <div className="ml-auto flex gap-2">
          <button
            type="button"
            onClick={() => salir.mutate(undefined, { onSuccess: () => navegar('/', { replace: true }) })}
            className="min-h-tactil rounded-boton bg-lavanda-100 px-4 text-etiqueta text-indigo-700"
          >
            Salir de modo padre
          </button>
          <button
            type="button"
            title="Cerrar la sesión de este dispositivo"
            aria-label="Cerrar la sesión de este dispositivo"
            onClick={() => {
              if (window.confirm('¿Cerrar la sesión de este dispositivo? Habrá que volver a entrar con correo y contraseña.')) {
                logout.mutate(undefined, { onSuccess: () => navegar('/login', { replace: true }) });
              }
            }}
            className="inline-flex min-h-tactil items-center rounded-boton px-3 text-tinta-600 hover:bg-superficie"
          >
            <LogOut className="size-5" aria-hidden="true" />
          </button>
        </div>
      </header>
      <main className={`mx-auto w-full flex-1 p-4 sm:p-6 ${amplia ? 'max-w-4xl lg:max-w-6xl' : 'max-w-4xl'}`}>
        <Outlet />
      </main>
    </div>
  );
}
