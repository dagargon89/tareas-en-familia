import { createBrowserRouter } from 'react-router';
import { EnConstruccion } from './ui/EnConstruccion.tsx';

/** Rutas de la SPA (doc 02 §4.2). Las pantallas se conectan a la API en su sprint. */
export const router = createBrowserRouter(
  [
    { path: '/', element: <EnConstruccion pantalla="Tareas en Familia" sprint="Sprint 1 (selector de perfil)" /> },
    { path: '/login', element: <EnConstruccion pantalla="Entrar" sprint="Sprint 1" /> },
    { path: '/hoy', element: <EnConstruccion pantalla="Mi día" sprint="Sprint 2" /> },
    { path: '/premios', element: <EnConstruccion pantalla="Premios" sprint="Sprint 4" /> },
    { path: '/logros', element: <EnConstruccion pantalla="Mis logros" sprint="Sprint 3" /> },
    { path: '/calendario', element: <EnConstruccion pantalla="Calendario" sprint="Sprint 5" /> },
    { path: '/mis-tareas', element: <EnConstruccion pantalla="Mis tareas" sprint="Sprint 5" /> },
    { path: '/padre/*', element: <EnConstruccion pantalla="Modo padre" sprint="Sprint 1" /> },
    // La galería solo existe en desarrollo; en el build de producción esta rama se elimina.
    ...(import.meta.env.DEV
      ? [{ path: '/galeria', lazy: async () => ({ Component: (await import('./features/galeria/Galeria.tsx')).Galeria }) }]
      : []),
    { path: '*', element: <EnConstruccion pantalla="No encontramos esta pantalla" sprint="—" /> },
  ],
  { basename: '/app' },
);
