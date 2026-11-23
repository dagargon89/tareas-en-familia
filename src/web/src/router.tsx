import { createBrowserRouter, Navigate } from 'react-router';
import { EntrarPerfil } from './features/acceso/EntrarPerfil.tsx';
import { RequiereModoPadre, RequierePerfil, RequiereSesion } from './features/acceso/Guardias.tsx';
import { Instalacion } from './features/acceso/Instalacion.tsx';
import { LoginPadre } from './features/acceso/LoginPadre.tsx';
import { ModoPadrePin } from './features/acceso/ModoPadrePin.tsx';
import { SelectorPerfil } from './features/acceso/SelectorPerfil.tsx';
import { HijaLayout } from './features/hija/HijaLayout.tsx';
import { Calendario } from './features/calendario/Calendario.tsx';
import { MiDia } from './features/mi-dia/MiDia.tsx';
import { Premios } from './features/premios/Premios.tsx';
import { MisLogros } from './features/progreso/MisLogros.tsx';
import { EditarTareaPropia, MisTareas } from './features/mis-tareas/MisTareas.tsx';
import { PadreLayout } from './features/padre/PadreLayout.tsx';

/**
 * Rutas de la SPA (doc 02 §4.2). Las pantallas del padre se cargan bajo demanda para que
 * "Mi día" baje lo mínimo en el dispositivo de las hijas (RNF-01).
 */
export const router = createBrowserRouter(
  [
    { path: '/login', element: <LoginPadre /> },
    { path: '/instalacion', element: <Instalacion /> },
    { path: '/', element: <RequiereSesion><SelectorPerfil /></RequiereSesion> },
    { path: '/entrar/:id', element: <RequiereSesion><EntrarPerfil /></RequiereSesion> },
    { path: '/modo-padre', element: <RequiereSesion><ModoPadrePin /></RequiereSesion> },
    {
      element: <RequierePerfil><HijaLayout /></RequierePerfil>,
      children: [
        { path: '/hoy', element: <MiDia /> },
        { path: '/premios', element: <Premios /> },
        { path: '/logros', element: <MisLogros /> },
        { path: '/calendario', element: <Calendario /> },
        // RF-19: Aventureras y Pro crean sus escolares; las Exploradoras regresan a Mi día.
        { path: '/mis-tareas', element: <RequierePerfil puedeCrear><MisTareas /></RequierePerfil> },
        { path: '/mis-tareas/nueva', element: <RequierePerfil puedeCrear><EditarTareaPropia /></RequierePerfil> },
        { path: '/mis-tareas/:id', element: <RequierePerfil puedeCrear><EditarTareaPropia /></RequierePerfil> },
      ],
    },
    {
      path: '/padre',
      element: <RequiereModoPadre><PadreLayout /></RequiereModoPadre>,
      children: [
        { index: true, lazy: async () => ({ Component: (await import('./features/padre/Panel.tsx')).Panel }) },
        { path: 'semana', lazy: async () => ({ Component: (await import('./features/calendario/Calendario.tsx')).SemanaPadre }) },
        { path: 'aprobaciones', lazy: async () => ({ Component: (await import('./features/padre/Aprobaciones.tsx')).Aprobaciones }) },
        { path: 'tareas', lazy: async () => ({ Component: (await import('./features/padre/Tareas.tsx')).Tareas }) },
        { path: 'perfiles', lazy: async () => ({ Component: (await import('./features/padre/Perfiles.tsx')).Perfiles }) },
        { path: 'premios', lazy: async () => ({ Component: (await import('./features/padre/Recompensas.tsx')).Recompensas }) },
        { path: 'metas', lazy: async () => ({ Component: (await import('./features/padre/Metas.tsx')).Metas }) },
        { path: 'ajustes', lazy: async () => ({ Component: (await import('./features/padre/Configuracion.tsx')).Configuracion }) },
      ],
    },
    // La galería solo existe en desarrollo; en el build de producción esta rama se elimina.
    ...(import.meta.env.DEV
      ? [{ path: '/galeria', lazy: async () => ({ Component: (await import('./features/galeria/Galeria.tsx')).Galeria }) }]
      : []),
    { path: '*', element: <Navigate to="/" replace /> },
  ],
  { basename: '/app' },
);
