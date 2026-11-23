import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, render as renderBase, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, MemoryRouter, RouterProvider } from 'react-router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Dia, Instancia } from '../../../src/api/ApiClient.ts';

vi.mock('canvas-confetti', () => ({ default: vi.fn() }));
vi.mock('../../../src/lib/sonidos.ts', () => ({ sonar: vi.fn() }));

const api = {
  sesion: vi.fn(),
  dia: vi.fn<(fecha?: string) => Promise<Dia>>(),
  marcarHecha: vi.fn<(id: number, foto?: File) => Promise<Instancia>>(),
};
vi.mock('../../../src/api/HttpApiClient.ts', async (importOriginal) => {
  const real = await importOriginal<typeof import('../../../src/api/HttpApiClient.ts')>();
  return { ...real, api };
});

const confetti = (await import('canvas-confetti')).default;
const { sonar } = await import('../../../src/lib/sonidos.ts');
const { MiDia, MiDiaVista } = await import('../../../src/features/mi-dia/MiDia.tsx');
const { useSesionStore } = await import('../../../src/stores/sesionStore.ts');
const { fechaLocal, sumarDias } = await import('../../../src/lib/fechas.ts');

const HOY = fechaLocal();
const instancia: Instancia = {
  id: 7, tarea_id: 1, perfil_id: 1, fecha: HOY, hora: null, fecha_entrega: null, titulo: 'Tender la cama',
  icono: 'cama', color: 'ambar', tipo: 'casa', materia: null, puntos: 3, estado: 'pendiente', requiere_foto: false,
  tiene_evidencia: false, evidencia_id: null, mensaje_devolucion: null, completada_en: null,
};
const dia = (i: Instancia[] = [instancia], fecha = HOY): Dia => ({ fecha, es_hoy: fecha === HOY, instancias: i, resumen: { total: i.length, hechas: 0, aprobadas: 0 } });

function montar() {
  const router = createMemoryRouter([{ path: '/hoy', element: <MiDia /> }], { initialEntries: ['/hoy'] });
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={qc}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
}

function movimientoReducido(activo: boolean) {
  window.matchMedia = vi.fn().mockImplementation((q: string) => ({ matches: activo && q.includes('reduce'), media: q, addEventListener: vi.fn(), removeEventListener: vi.fn() })) as unknown as typeof window.matchMedia;
}

describe('CP-032 · Tocar "Listo" celebra y llama marcarHecha', () => {
  const matchMediaOriginal = window.matchMedia;
  beforeEach(() => {
    vi.mocked(confetti).mockClear();
    vi.mocked(sonar).mockClear();
    api.sesion.mockResolvedValue({ usuario: { id: 1, nombre: 'P' }, perfil_activo: { id: 1, apodo: 'Luna', avatar: 'conejo', color: 'rosa', nivel_ux: 'exploradoras', sonidos: true, tema: 'claro', bloqueado_hasta: null }, modo_padre_hasta: null });
    api.dia.mockResolvedValue(dia());
    api.marcarHecha.mockResolvedValue({ ...instancia, estado: 'hecha', completada_en: new Date().toISOString() });
    useSesionStore.getState().setNivel('exploradoras');
    useSesionStore.getState().setSonidos(true);
  });
  afterEach(() => {
    window.matchMedia = matchMediaOriginal;
  });

  it('llama marcarHecha sin puntos, muestra confeti y suena', async () => {
    movimientoReducido(false);
    montar();
    await userEvent.click(await screen.findByRole('button', { name: /listo/i }));
    await waitFor(() => expect(confetti).toHaveBeenCalledTimes(1));
    expect(api.marcarHecha).toHaveBeenCalledWith(7, undefined);
    expect(sonar).toHaveBeenCalledWith('completar', true);
    expect(await screen.findByText('¡Lo lograste! ⭐ Ahora papá la revisa')).toBeInTheDocument();
  });

  it('con prefers-reduced-motion no hay confeti, pero sí se marca', async () => {
    movimientoReducido(true);
    montar();
    await userEvent.click(await screen.findByRole('button', { name: /listo/i }));
    await waitFor(() => expect(api.marcarHecha).toHaveBeenCalledWith(7, undefined));
    expect(confetti).not.toHaveBeenCalled();
  });

  it('si el servidor rechaza, la tarjeta vuelve a pendiente con un aviso amable', async () => {
    movimientoReducido(false);
    api.marcarHecha.mockRejectedValueOnce(new Error('red'));
    montar();
    await userEvent.click(await screen.findByRole('button', { name: /listo/i }));
    expect(await screen.findByText('No se pudo guardar. Intenta otra vez.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /listo/i })).toBeInTheDocument();
    expect(confetti).not.toHaveBeenCalled();
  });
});

describe('MiDiaVista (RF-04, RF-05)', () => {
  // La vista enlaza a "Mis tareas" (RF-19), así que necesita un router.
  const render = (ui: React.ReactElement) => renderBase(ui, { wrapper: MemoryRouter });
  const props = { hoy: HOY, apodo: 'Sofi', nivel: 'aventureras' as const, marcando: null, aviso: null, onListo: vi.fn() };

  it('no deja ir más allá de 7 días atrás ni adelante', () => {
    const { rerender } = render(<MiDiaVista {...props} dia={dia([], sumarDias(HOY, -7))} onCambiarDia={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Día anterior' })).toBeDisabled();
    rerender(<MiDiaVista {...props} dia={dia([], sumarDias(HOY, 7))} onCambiarDia={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Día siguiente' })).toBeDisabled();
  });

  it('en un día futuro no ofrece "Listo"', () => {
    render(<MiDiaVista {...props} dia={dia([{ ...instancia, fecha: sumarDias(HOY, 1) }], sumarDias(HOY, 1))} onCambiarDia={vi.fn()} />);
    expect(screen.queryByRole('button', { name: /listo/i })).not.toBeInTheDocument();
  });

  it('día vacío usa el texto del nivel', () => {
    render(<MiDiaVista {...props} nivel="exploradoras" dia={dia([])} onCambiarDia={vi.fn()} />);
    expect(screen.getByText('¡Hoy no hay misiones! Día libre 🎈')).toBeInTheDocument();
  });
});

describe('MiDiaVista · acceso a escolares propias (RF-19)', () => {
  const props = { hoy: HOY, apodo: 'Sofi', marcando: null, aviso: null, onListo: vi.fn(), onCambiarDia: vi.fn(), dia: dia([]) };
  it('Aventureras ven "Agregar tarea de la escuela"; Exploradoras no', () => {
    const { unmount } = renderBase(<MiDiaVista {...props} nivel="aventureras" />, { wrapper: MemoryRouter });
    expect(screen.getByRole('link', { name: /agregar tarea de la escuela/i })).toHaveAttribute('href', '/mis-tareas/nueva');
    unmount();
    renderBase(<MiDiaVista {...props} nivel="exploradoras" />, { wrapper: MemoryRouter });
    expect(screen.queryByRole('link', { name: /agregar tarea de la escuela/i })).not.toBeInTheDocument();
  });
});
