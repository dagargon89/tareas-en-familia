import { createBrowserRouter, Navigate, Outlet } from 'react-router';
import { EntrarPerfil } from './features/acceso/EntrarPerfil.tsx';
import { RequiereModoPadre, RequierePerfil, RequiereSesion } from './features/acceso/Guardias.tsx';
import { LoginPadre } from './features/acceso/LoginPadre.tsx';
import { ModoPadrePin } from './features/acceso/ModoPadrePin.tsx';
import { SelectorPerfil } from './features/acceso/SelectorPerfil.tsx';
import { HijaLayout } from './features/hija/HijaLayout.tsx';
import { MiDia } from './features/mi-dia/MiDia.tsx';
import { PadreLayout } from './features/padre/PadreLayout.tsx';

/**
 * Rutas de la SPA (doc 02 §4.2). Solo el acceso y "Mi día" van en el JavaScript inicial; el resto
 * de pantallas de las hijas y todas las del padre se cargan bajo demanda (RNF-01).
 */
export const router = createBrowserRouter(
  [
    { path: '/login', element: <LoginPadre /> },
    { path: '/instalacion', lazy: async () => ({ Component: (await import('./features/acceso/Instalacion.tsx')).Instalacion }) },
    { path: '/', element: <RequiereSesion><SelectorPerfil /></RequiereSesion> },
    { path: '/entrar/:id', element: <RequiereSesion><EntrarPerfil /></RequiereSesion> },
    { path: '/modo-padre', element: <RequiereSesion><ModoPadrePin /></RequiereSesion> },
    {
      element: <RequierePerfil><HijaLayout /></RequierePerfil>,
      children: [
        { path: '/hoy', element: <MiDia /> },
        { path: '/premios', lazy: async () => ({ Component: (await import('./features/premios/Premios.tsx')).Premios }) },
        { path: '/logros', lazy: async () => ({ Component: (await import('./features/progreso/MisLogros.tsx')).MisLogros }) },
        { path: '/calendario', lazy: async () => ({ Component: (await import('./features/calendario/Calendario.tsx')).Calendario }) },
        { path: '/chat', lazy: async () => ({ Component: (await import('./features/chat/ChatPage.tsx')).ChatPage }) },
        {
          // RF-19: Aventureras y Pro crean sus escolares; las Exploradoras regresan a Mi día.
          element: <RequierePerfil puedeCrear><Outlet /></RequierePerfil>,
          children: [
            { path: '/mis-tareas', lazy: async () => ({ Component: (await import('./features/mis-tareas/MisTareas.tsx')).MisTareas }) },
            { path: '/mis-tareas/nueva', lazy: async () => ({ Component: (await import('./features/mis-tareas/MisTareas.tsx')).EditarTareaPropia }) },
            { path: '/mis-tareas/:id', lazy: async () => ({ Component: (await import('./features/mis-tareas/MisTareas.tsx')).EditarTareaPropia }) },
          ],
        },
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
        { path: 'chat', lazy: async () => ({ Component: (await import('./features/chat/ChatPage.tsx')).ChatPage }) },
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
