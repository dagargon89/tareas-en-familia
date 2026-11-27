import { CalendarDays, Gift, ListChecks, Medal, MessageCircle, Moon, MonitorSmartphone, Sun, SunMedium, Volume2, VolumeX } from 'lucide-react';
import { NavLink, Outlet, useMatch, useNavigate } from 'react-router';
import type { NivelUx, PerfilPublico } from '../../api/ApiClient.ts';
import { useNoLeidosChat } from '../../hooks/useNoLeidosChat.ts';
import { usePreferencias, useSalirPerfil, useSesion } from '../../hooks/useSesion.ts';
import { cerrarSesionChat } from '../../lib/chatSesion.ts';
import { aplicarTema } from '../../niveles/nivel.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { BotonAsistente } from '../asistente/BotonAsistente.tsx';
import { FormasVivas } from '../../ui/FormasVivas.tsx';
import { SaltarContenido } from '../../ui/SaltarContenido.tsx';

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

const SIGUIENTE_TEMA: Record<PerfilPublico['tema'], PerfilPublico['tema']> = { claro: 'oscuro', oscuro: 'sistema', sistema: 'claro' };
const TEMA = {
  claro: { Icono: SunMedium, texto: 'Tema claro' },
  oscuro: { Icono: Moon, texto: 'Tema oscuro' },
  sistema: { Icono: MonitorSmartphone, texto: 'Tema del dispositivo' },
} as const;

interface VistaProps {
  perfil: PerfilPublico;
  onCambiar: () => void;
  onSonidos?: (activos: boolean) => void;
  onTema?: (tema: PerfilPublico['tema']) => void;
  noLeidosChat?: number;
}

export function HijaLayoutVista({ perfil, onCambiar, onSonidos, onTema, noLeidosChat = 0 }: VistaProps) {
  const iconoBoton = 'inline-flex size-11 items-center justify-center rounded-full text-indigo-700 hover:bg-lavanda-100';
  const tema = TEMA[perfil.tema];
  const Avatar = avatarDe(perfil.avatar);
  const secciones = seccionesDe(perfil.nivel_ux);
  // "Mi semana" y "Chat" usan el ancho del escritorio para su tablero/conversaciones.
  const esSemana = useMatch('/calendario') !== null;
  const esChat = useMatch('/chat') !== null;
  const amplia = esSemana || esChat;
  return (
    <div className="relative flex min-h-dvh flex-col">
      <SaltarContenido />
      <FormasVivas />
      <header className="flex items-center gap-2 px-4 pt-4 sm:gap-3 sm:px-8">
        <span className={`flex size-12 shrink-0 items-center justify-center rounded-full ${FONDO_COLOR[perfil.color]}`}>
          <Avatar className="size-6" aria-hidden="true" />
        </span>
        <span className="truncate text-titulo">{perfil.apodo}</span>
        <div className="ml-auto flex items-center gap-1">
          <NavLink
            to="/chat"
            aria-label={noLeidosChat > 0 ? `Chat familiar, ${noLeidosChat} sin leer` : 'Chat familiar'}
            title="Chat familiar"
            className={({ isActive }) =>
              `relative inline-flex size-11 items-center justify-center rounded-full transition-colors ${
                isActive ? 'bg-indigo-600 text-blanco' : 'text-indigo-700 hover:bg-lavanda-100'
              }`
            }
          >
            <MessageCircle className="size-5" aria-hidden="true" />
            {noLeidosChat > 0 && (
              <span className="absolute top-1 right-1 inline-flex min-w-4 items-center justify-center rounded-boton bg-rosa-200 px-1 text-[11px] leading-4 font-bold text-tinta-900">
                {noLeidosChat}
              </span>
            )}
          </NavLink>
          <BotonAsistente claveActor={`hija:${perfil.id}`} nombreActor={perfil.apodo} nivel={perfil.nivel_ux} />
          {onSonidos && (
            <button type="button" className={iconoBoton} aria-pressed={perfil.sonidos} aria-label={perfil.sonidos ? 'Apagar sonidos' : 'Encender sonidos'} onClick={() => onSonidos(!perfil.sonidos)}>
              {perfil.sonidos ? <Volume2 className="size-5" aria-hidden="true" /> : <VolumeX className="size-5" aria-hidden="true" />}
            </button>
          )}
          {/* Modo oscuro solo en Aventureras y Pro (doc 08 §3). */}
          {onTema && perfil.nivel_ux !== 'exploradoras' && (
            <button type="button" className={iconoBoton} aria-label={`${tema.texto}. Cambiar tema`} title={tema.texto} onClick={() => onTema(SIGUIENTE_TEMA[perfil.tema])}>
              <tema.Icono className="size-5" aria-hidden="true" />
            </button>
          )}
        </div>
        <button type="button" onClick={onCambiar} className="min-h-11 shrink-0 rounded-boton px-3 text-etiqueta text-indigo-700 hover:bg-lavanda-100 sm:px-4">
          Cambiar de perfil
        </button>
      </header>
      <main id="contenido-principal" className={`mx-auto w-full flex-1 px-4 pt-4 pb-32 sm:px-8 ${amplia ? 'max-w-3xl lg:max-w-6xl' : 'max-w-3xl'}`}>
        <Outlet />
      </main>
      <nav aria-label="Secciones" className="fixed inset-x-0 bottom-0 z-20 border-t border-superficie bg-fondo px-2 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
        <ul className="mx-auto flex max-w-3xl justify-around gap-1">
          {secciones.map(({ a, texto, Icono }) => (
            <li key={a} className="min-w-0 flex-1">
              <NavLink
                to={a}
                className={({ isActive }) =>
                  `flex min-h-tactil flex-col items-center justify-center gap-1 rounded-tarjeta-interna px-1.5 py-1 text-etiqueta transition-colors ${isActive ? 'bg-lavanda-100 text-indigo-700' : 'text-tinta-600 hover:bg-superficie'}`
                }
              >
                <Icono style={{ width: 'calc(var(--tf-icono) * 0.75)', height: 'calc(var(--tf-icono) * 0.75)' }} aria-hidden="true" />
                <span className="max-w-full truncate">{texto}</span>
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
  const preferencias = usePreferencias();
  const setSonidos = useSesionStore((s) => s.setSonidos);
  const perfilId = data?.perfil_activo?.id ?? null;
  const noLeidosChat = useNoLeidosChat(perfilId !== null ? `hija:${perfilId}` : null);
  if (!data?.perfil_activo) return null;
  return (
    <HijaLayoutVista
      perfil={data.perfil_activo}
      noLeidosChat={noLeidosChat}
      onCambiar={() => {
        void cerrarSesionChat();
        salir.mutate(undefined, { onSuccess: () => navegar('/', { replace: true }) });
      }}
      onSonidos={(sonidos) => {
        setSonidos(sonidos);
        preferencias.mutate({ sonidos });
      }}
      onTema={(tema) => {
        aplicarTema(tema);
        preferencias.mutate({ tema });
      }}
    />
  );
}
