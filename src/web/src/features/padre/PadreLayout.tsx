import { useQuery } from '@tanstack/react-query';
import { CalendarDays, ChartColumn, Gift, Inbox, ListChecks, LogOut, MonitorSmartphone, Moon, Settings, SunMedium, Target, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { NavLink, Outlet, useMatch, useNavigate } from 'react-router';
import { api } from '../../api/HttpApiClient.ts';
import { useLogout, useSalirModoPadre, useSesion } from '../../hooks/useSesion.ts';
import { aplicarNivel, aplicarTema } from '../../niveles/nivel.ts';
import { DialogoConfirmacion } from '../../ui/DialogoConfirmacion.tsx';
import { SaltarContenido } from '../../ui/SaltarContenido.tsx';

type TemaPadre = 'sistema' | 'claro' | 'oscuro';
const SIGUIENTE_TEMA: Record<TemaPadre, TemaPadre> = { sistema: 'oscuro', oscuro: 'claro', claro: 'sistema' };
const INFO_TEMA = {
  sistema: { Icono: MonitorSmartphone, texto: 'Tema del dispositivo' },
  claro: { Icono: SunMedium, texto: 'Tema claro' },
  oscuro: { Icono: Moon, texto: 'Tema oscuro' },
} as const;

const SECCIONES = [
  { a: '/padre', texto: 'Panel', Icono: ChartColumn, fin: true },
  { a: '/padre/aprobaciones', texto: 'Aprobar', Icono: Inbox, badge: 'aprobaciones' as const },
  { a: '/padre/tareas', texto: 'Tareas', Icono: ListChecks },
  { a: '/padre/semana', texto: 'Semana', Icono: CalendarDays },
  { a: '/padre/perfiles', texto: 'Perfiles', Icono: Users },
  { a: '/padre/premios', texto: 'Premios', Icono: Gift, badge: 'premios' as const },
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
  const [confirmarCierre, setConfirmarCierre] = useState(false);
  const [tema, setTema] = useState<TemaPadre>('sistema');
  // La semana usa el ancho del escritorio para su tablero; las demás pantallas, una columna cómoda.
  const amplia = useMatch('/padre/semana') !== null;

  const panel = useQuery({ queryKey: ['admin', 'panel'], queryFn: () => api.panel(), staleTime: 60_000 });
  const conteos = {
    aprobaciones: panel.data?.hijas.reduce((n, h) => n + h.por_aprobar, 0) ?? 0,
    premios: panel.data?.canjes_pendientes.length ?? 0,
  };

  useEffect(() => {
    aplicarNivel('pro');
    aplicarTema(tema);
  }, [tema]);
  useSesion(60_000);

  const infoTema = INFO_TEMA[tema];

  return (
    <div className="flex min-h-dvh flex-col">
      <SaltarContenido />
      <header className="sticky top-0 z-20 flex flex-wrap items-center gap-3 border-b border-superficie bg-fondo px-4 py-2">
        <span className="text-titulo">Modo padre</span>
        <nav aria-label="Secciones" className="order-last -mx-4 flex w-[calc(100%+2rem)] gap-1 overflow-x-auto px-4 sm:order-none sm:mx-0 sm:w-auto sm:flex-1 sm:px-0">
          {SECCIONES.map(({ a, texto, Icono, fin, badge }) => {
            const totalBadge = badge ? conteos[badge] : 0;
            return (
              <NavLink
                key={a}
                to={a}
                end={fin}
                className={({ isActive }) =>
                  `inline-flex min-h-tactil shrink-0 items-center gap-2 rounded-boton px-4 text-etiqueta transition-colors ${isActive ? 'bg-indigo-600 text-blanco' : 'text-tinta-600 hover:bg-superficie'}`
                }
              >
                {({ isActive }) => (
                  <>
                    <Icono className="size-4" aria-hidden="true" />
                    {texto}
                    {totalBadge > 0 && (
                      <span
                        className={`inline-flex min-w-5 items-center justify-center rounded-boton px-1.5 py-0.5 text-[12px] leading-none font-semibold ${
                          isActive ? 'bg-blanco text-indigo-600' : 'bg-rosa-200 text-tinta-900'
                        }`}
                      >
                        {totalBadge}
                      </span>
                    )}
                  </>
                )}
              </NavLink>
            );
          })}
        </nav>
        <div className="ml-auto flex items-center gap-2">
          <button
            type="button"
            title={infoTema.texto}
            aria-label={`${infoTema.texto}. Cambiar tema`}
            onClick={() => setTema(SIGUIENTE_TEMA[tema])}
            className="inline-flex min-h-tactil items-center rounded-boton px-3 text-tinta-600 transition-colors hover:bg-superficie"
          >
            <infoTema.Icono className="size-5" aria-hidden="true" />
          </button>
          <button
            type="button"
            onClick={() => salir.mutate(undefined, { onSuccess: () => navegar('/', { replace: true }) })}
            className="min-h-tactil rounded-boton bg-lavanda-100 px-4 text-etiqueta text-indigo-700 transition hover:brightness-95"
          >
            Salir de modo padre
          </button>
          <button
            type="button"
            title="Cerrar la sesión de este dispositivo"
            aria-label="Cerrar la sesión de este dispositivo"
            onClick={() => setConfirmarCierre(true)}
            className="inline-flex min-h-tactil items-center rounded-boton px-3 text-tinta-600 transition-colors hover:bg-superficie"
          >
            <LogOut className="size-5" aria-hidden="true" />
          </button>
        </div>
      </header>
      <main id="contenido-principal" className={`mx-auto w-full flex-1 p-4 sm:p-6 ${amplia ? 'max-w-4xl lg:max-w-6xl' : 'max-w-4xl'}`}>
        <Outlet />
      </main>
      <DialogoConfirmacion
        abierto={confirmarCierre}
        titulo="¿Cerrar la sesión de este dispositivo?"
        mensaje={'Sus hijas ya no podrán entrar con su avatar y PIN hasta que papá o mamá vuelvan a iniciar sesión con correo y contraseña.\n\nPara que entre otra hija, no hace falta cerrar la sesión: usen «Cambiar de perfil».'}
        textoConfirmar="Cerrar sesión"
        textoCancelar="Cancelar"
        ocupado={logout.isPending}
        onCancelar={() => setConfirmarCierre(false)}
        onConfirmar={() => {
          setConfirmarCierre(false);
          logout.mutate(undefined, { onSuccess: () => navegar('/login', { replace: true }) });
        }}
      />
    </div>
  );
}
