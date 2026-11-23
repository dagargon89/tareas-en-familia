import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Sesion } from '../../src/api/ApiClient.ts';
import { problemaPin } from '../../src/lib/pin.ts';

const sesionMock = vi.fn<() => Promise<Sesion>>();

vi.mock('../../src/api/HttpApiClient.ts', async (importOriginal) => {
  const real = await importOriginal<typeof import('../../src/api/HttpApiClient.ts')>();
  return { ...real, api: { sesion: () => sesionMock(), perfiles: () => Promise.resolve([]) } };
});

const { ErrorApi } = await import('../../src/api/HttpApiClient.ts');
const { RequiereModoPadre, RequierePerfil, RequiereSesion } = await import('../../src/features/acceso/Guardias.tsx');
const { FormPerfil } = await import('../../src/features/padre/FormPerfil.tsx');

const PERFIL = { id: 2, apodo: 'Sofi', avatar: 'gato', color: 'menta', nivel_ux: 'aventureras', sonidos: true, tema: 'claro', bloqueado_hasta: null } as const;

function montar(ruta: string) {
  const router = createMemoryRouter(
    [
      { path: '/login', element: <p>pantalla login</p> },
      { path: '/', element: <RequiereSesion><p>pantalla selector</p></RequiereSesion> },
      { path: '/hoy', element: <RequierePerfil><p>pantalla hoy</p></RequierePerfil> },
      { path: '/modo-padre', element: <p>pantalla pin padre</p> },
      { path: '/padre', element: <RequiereModoPadre><p>pantalla padre</p></RequiereModoPadre> },
    ],
    { initialEntries: [ruta] },
  );
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={qc}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
}

describe('problemaPin (doc 04 §3.1, parte de CP-010)', () => {
  it.each(['1234', '4321', '0123', '0000', '7777', '6789'])('rechaza %s', (pin) => {
    expect(problemaPin(pin)).not.toBeNull();
  });
  it.each(['2580', '1357', '9024', '1123'])('acepta %s', (pin) => {
    expect(problemaPin(pin)).toBeNull();
  });
  it('exige 4 dígitos', () => {
    expect(problemaPin('123')).not.toBeNull();
    expect(problemaPin('12a4')).not.toBeNull();
  });
});

describe('Guardias de ruta', () => {
  beforeEach(() => {
    sesionMock.mockReset();
  });

  it('sin sesión de dispositivo manda al login', async () => {
    sesionMock.mockImplementation(async () => {
      throw new ErrorApi(401, { codigo: 'SIN_SESION', mensaje: 'Sin sesión' });
    });
    montar('/');
    expect(await screen.findByText('pantalla login')).toBeInTheDocument();
  });

  it('con sesión pero sin perfil activo, /hoy vuelve al selector', async () => {
    sesionMock.mockResolvedValue({ usuario: { id: 1, nombre: 'David' }, perfil_activo: null, modo_padre_hasta: null });
    montar('/hoy');
    expect(await screen.findByText('pantalla selector')).toBeInTheDocument();
  });

  it('con perfil activo entra a /hoy', async () => {
    sesionMock.mockResolvedValue({ usuario: { id: 1, nombre: 'David' }, perfil_activo: PERFIL, modo_padre_hasta: null });
    montar('/hoy');
    expect(await screen.findByText('pantalla hoy')).toBeInTheDocument();
  });

  it('modo padre vencido pide el PIN del padre', async () => {
    const vencido = new Date(Date.now() - 60_000).toISOString();
    sesionMock.mockResolvedValue({ usuario: { id: 1, nombre: 'David' }, perfil_activo: null, modo_padre_hasta: vencido });
    montar('/padre');
    expect(await screen.findByText('pantalla pin padre')).toBeInTheDocument();
  });

  it('modo padre vigente deja entrar', async () => {
    const vigente = new Date(Date.now() + 5 * 60_000).toISOString();
    sesionMock.mockResolvedValue({ usuario: { id: 1, nombre: 'David' }, perfil_activo: null, modo_padre_hasta: vigente });
    montar('/padre');
    expect(await screen.findByText('pantalla padre')).toBeInTheDocument();
  });
});

describe('FormPerfil (RF-17)', () => {
  it('no envía con PIN trivial y avisa sin tono de error', async () => {
    const onGuardar = vi.fn();
    render(<FormPerfil perfil={null} enviando={false} onGuardar={onGuardar} onCancelar={vi.fn()} />);
    await userEvent.type(screen.getByLabelText('Apodo'), 'Luna');
    await userEvent.type(screen.getByLabelText('PIN de 4 dígitos'), '1234');
    await userEvent.click(screen.getByRole('button', { name: 'Crear perfil' }));
    expect(onGuardar).not.toHaveBeenCalled();
    expect(screen.getByText('Evita secuencias como 1234 o 4321')).toBeInTheDocument();
  });

  it('envía solo los campos permitidos (sin datos personales extra)', async () => {
    const onGuardar = vi.fn();
    render(<FormPerfil perfil={null} enviando={false} onGuardar={onGuardar} onCancelar={vi.fn()} />);
    await userEvent.type(screen.getByLabelText('Apodo'), '  Luna ');
    await userEvent.type(screen.getByLabelText('PIN de 4 dígitos'), '2580');
    await userEvent.click(screen.getByRole('button', { name: 'Crear perfil' }));
    expect(onGuardar).toHaveBeenCalledWith({ apodo: 'Luna', avatar: 'gato', color: 'menta', nivel_ux: 'aventureras', pin: '2580' });
  });

  it('al editar, el PIN vacío no se envía', async () => {
    const onGuardar = vi.fn();
    render(<FormPerfil perfil={{ ...PERFIL, activo: true, orden: 0, pin_intentos: 0 }} enviando={false} onGuardar={onGuardar} onCancelar={vi.fn()} />);
    await userEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }));
    expect(onGuardar).toHaveBeenCalledWith(expect.not.objectContaining({ pin: expect.anything() }));
  });
});
