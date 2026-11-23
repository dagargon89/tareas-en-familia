import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { EstadoInstalacion, Sesion } from '../../../src/api/ApiClient.ts';

const api = {
  sesion: vi.fn<() => Promise<Sesion>>(),
  estadoInstalacion: vi.fn<() => Promise<EstadoInstalacion>>(),
};
vi.mock('../../../src/api/HttpApiClient.ts', async (importOriginal) => {
  const real = await importOriginal<typeof import('../../../src/api/HttpApiClient.ts')>();
  return { ...real, api };
});

const { LoginPadre } = await import('../../../src/features/acceso/LoginPadre.tsx');
const { InstalacionVista } = await import('../../../src/features/acceso/Instalacion.tsx');
const { validarInstalacion } = await import('../../../src/features/acceso/validarInstalacion.ts');

const SIN_SESION: Sesion = { usuario: null, perfil_activo: null, modo_padre_hasta: null };
const valido = { nombre: 'David', email: 'papa@ejemplo.mx', password: 'unaClaveLarga1', confirmar: 'unaClaveLarga1', pin: '2580', token: '' };

describe('validarInstalacion (RF-20)', () => {
  it('acepta datos válidos sin token cuando no se requiere', () => {
    expect(validarInstalacion(valido, false)).toEqual({});
  });
  it('rechaza contraseña corta, confirmación distinta, PIN trivial y correo inválido', () => {
    const e = validarInstalacion({ ...valido, email: 'no-es-correo', password: 'corta', confirmar: 'otra', pin: '1234' }, false);
    expect(Object.keys(e).sort()).toEqual(['confirmar', 'email', 'password', 'pin']);
  });
  it('exige token cuando el servidor lo pide', () => {
    expect(validarInstalacion(valido, true).token).toBeDefined();
    expect(validarInstalacion({ ...valido, token: 'abc' }, true).token).toBeUndefined();
  });
});

describe('Login redirige a la instalación', () => {
  beforeEach(() => {
    api.sesion.mockResolvedValue(SIN_SESION);
  });

  function montar() {
    const router = createMemoryRouter(
      [
        { path: '/login', element: <LoginPadre /> },
        { path: '/instalacion', element: <p>pantalla instalación</p> },
      ],
      { initialEntries: ['/login'] },
    );
    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <RouterProvider router={router} />
      </QueryClientProvider>,
    );
  }

  it('sin usuarios en la base, el login manda a "Configura la app"', async () => {
    api.estadoInstalacion.mockResolvedValue({ requerida: true, requiere_token: false });
    montar();
    expect(await screen.findByText('pantalla instalación')).toBeInTheDocument();
  });

  it('con usuarios, se queda en el login', async () => {
    api.estadoInstalacion.mockResolvedValue({ requerida: false, requiere_token: false });
    montar();
    expect(await screen.findByRole('button', { name: 'Entrar' })).toBeInTheDocument();
  });

  it('si la API no responde, muestra el login', async () => {
    api.estadoInstalacion.mockRejectedValue(new Error('sin red'));
    montar();
    expect(await screen.findByRole('button', { name: 'Entrar' })).toBeInTheDocument();
  });
});

describe('InstalacionVista', () => {
  async function llenar() {
    await userEvent.type(screen.getByLabelText('Tu nombre'), 'David');
    await userEvent.type(screen.getByLabelText('Correo'), 'papa@ejemplo.mx');
    await userEvent.type(screen.getByLabelText('Contraseña'), 'unaClaveLarga1');
    await userEvent.type(screen.getByLabelText('Repite la contraseña'), 'unaClaveLarga1');
    await userEvent.type(screen.getByLabelText('PIN de modo padre'), '2580');
  }

  it('sin token requerido no muestra el campo ni lo envía', async () => {
    const onInstalar = vi.fn();
    render(<InstalacionVista requiereToken={false} enviando={false} error={null} onInstalar={onInstalar} />);
    expect(screen.queryByLabelText('Token de instalación')).not.toBeInTheDocument();
    await llenar();
    await userEvent.click(screen.getByRole('button', { name: 'Crear mi cuenta' }));
    expect(onInstalar).toHaveBeenCalledWith({ nombre: 'David', email: 'papa@ejemplo.mx', password: 'unaClaveLarga1', pin: '2580' });
  });

  it('con token requerido lo pide y lo envía', async () => {
    const onInstalar = vi.fn();
    render(<InstalacionVista requiereToken enviando={false} error={null} onInstalar={onInstalar} />);
    await llenar();
    await userEvent.click(screen.getByRole('button', { name: 'Crear mi cuenta' }));
    expect(onInstalar).not.toHaveBeenCalled();
    await userEvent.type(screen.getByLabelText('Token de instalación'), ' secreto ');
    await userEvent.click(screen.getByRole('button', { name: 'Crear mi cuenta' }));
    expect(onInstalar).toHaveBeenCalledWith(expect.objectContaining({ token: 'secreto' }));
  });
});
