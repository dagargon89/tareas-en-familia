import { MutationCache, QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { LazyMotion, MotionConfig } from 'motion/react';
import { RouterProvider } from 'react-router';
import { ErrorApi } from './api/HttpApiClient.ts';
import { CLAVE_SESION } from './hooks/useSesion.ts';
import { router } from './router.tsx';

const cargarFuncionesMotion = () => import('./lib/funcionesMotion.ts').then((m) => m.default);

/**
 * Si cualquier petición dice que la sesión, el perfil o el modo padre ya no son válidos,
 * se refresca la sesión y las guardias de ruta redirigen solas.
 */
const CODIGOS_DE_SESION = new Set(['SIN_SESION', 'SIN_PERFIL', 'MODO_PADRE_REQUERIDO']);

function alFallar(error: unknown) {
  if (error instanceof ErrorApi && CODIGOS_DE_SESION.has(error.codigo)) {
    void queryClient.invalidateQueries({ queryKey: CLAVE_SESION });
  }
}

const queryClient: QueryClient = new QueryClient({
  queryCache: new QueryCache({ onError: alFallar }),
  mutationCache: new MutationCache({ onError: alFallar }),
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: true,
      retry: (intentos, error) => !(error instanceof ErrorApi && error.status < 500) && intentos < 1,
    },
  },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      {/* Respeta prefers-reduced-motion en todas las animaciones de Motion (RNF-04). */}
      <MotionConfig reducedMotion="user">
        {/* Las funciones de animación se cargan aparte; `strict` impide usar `motion.*` (cargaría todo). */}
        <LazyMotion features={cargarFuncionesMotion} strict>
          <RouterProvider router={router} />
        </LazyMotion>
      </MotionConfig>
    </QueryClientProvider>
  );
}
