import { createBrowserRouter, Navigate } from 'react-router';
import { EntrarPerfil } from './features/acceso/EntrarPerfil.tsx';
import { RequiereModoPadre, RequierePerfil, RequiereSesion } from './features/acceso/Guardias.tsx';
import { LoginPadre } from './features/acceso/LoginPadre.tsx';
import { ModoPadrePin } from './features/acceso/ModoPadrePin.tsx';
import { SelectorPerfil } from './features/acceso/SelectorPerfil.tsx';
import { HijaLayout } from './features/hija/HijaLayout.tsx';
import { HoyProvisional } from './features/hija/HoyProvisional.tsx';
import { PadreLayout } from './features/padre/PadreLayout.tsx';
import { Perfiles } from './features/padre/Perfiles.tsx';
import { EnConstruccion } from './ui/EnConstruccion.tsx';

/** Rutas de la SPA (doc 02 §4.2). Las pantallas sin sprint muestran un marcador. */
export const router = createBrowserRouter(
  [
    { path: '/login', element: <LoginPadre /> },
    { path: '/', element: <RequiereSesion><SelectorPerfil /></RequiereSesion> },
    { path: '/entrar/:id', element: <RequiereSesion><EntrarPerfil /></RequiereSesion> },
    { path: '/modo-padre', element: <RequiereSesion><ModoPadrePin /></RequiereSesion> },
    {
      element: <RequierePerfil><HijaLayout /></RequierePerfil>,
      children: [
        { path: '/hoy', element: <HoyProvisional /> },
        { path: '/premios', element: <EnConstruccion pantalla="Premios" sprint="Sprint 4" /> },
        { path: '/logros', element: <EnConstruccion pantalla="Mis logros" sprint="Sprint 3" /> },
        { path: '/calendario', element: <EnConstruccion pantalla="Semana" sprint="Sprint 5" /> },
        { path: '/mis-tareas', element: <RequierePerfil soloPro><EnConstruccion pantalla="Mis tareas" sprint="Sprint 5" /></RequierePerfil> },
      ],
    },
    {
      path: '/padre',
      element: <RequiereModoPadre><PadreLayout /></RequiereModoPadre>,
      children: [
        { index: true, element: <EnConstruccion pantalla="Panel" sprint="Sprint 5" /> },
        { path: 'aprobaciones', element: <EnConstruccion pantalla="Aprobaciones" sprint="Sprint 3" /> },
        { path: 'tareas', element: <EnConstruccion pantalla="Tareas" sprint="Sprint 2" /> },
        { path: 'perfiles', element: <Perfiles /> },
        { path: 'premios', element: <EnConstruccion pantalla="Premios" sprint="Sprint 4" /> },
        { path: 'metas', element: <EnConstruccion pantalla="Metas" sprint="Sprint 4" /> },
        { path: 'ajustes', element: <EnConstruccion pantalla="Ajustes" sprint="Sprint 4" /> },
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
