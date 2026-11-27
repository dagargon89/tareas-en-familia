import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../src/api/HttpApiClient.ts';
import { AsistenteDrawer } from '../../src/features/asistente/AsistenteDrawer.tsx';

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

function renderConQuery(ui: React.ReactNode) {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>);
}

describe('AsistenteDrawer', () => {
  it('muestra saludo y sugerencias de Exploradoras, envía pregunta y renderiza respuesta', async () => {
    const user = userEvent.setup();
    const spy = vi.spyOn(api, 'preguntarAsistente').mockResolvedValue({
      respuesta: 'Hoy te falta Tender la cama (5 estrellas).',
      sugerencias: ['¿Para qué premio me alcanzan mis estrellas?'],
      modo_demo: true,
    });

    renderConQuery(
      <AsistenteDrawer
        abierto
        onCerrar={vi.fn()}
        claveActor="hija:1"
        nombreActor="Luna"
        nivel="exploradoras"
      />,
    );

    expect(screen.getByText(/¡Hola Luna!/i)).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: '¿Qué misiones me faltan hoy?' }));

    expect(spy).toHaveBeenCalledWith('¿Qué misiones me faltan hoy?', []);
    expect(await screen.findByText('Hoy te falta Tender la cama (5 estrellas).')).toBeInTheDocument();
    expect(screen.getByText(/Modo demostración local activo/i)).toBeInTheDocument();
  });

  it('limpia la conversación al cambiar de actor para no mezclar historiales', async () => {
    const user = userEvent.setup();
    vi.spyOn(api, 'preguntarAsistente').mockResolvedValue({
      respuesta: 'Respuesta para Luna',
      sugerencias: [],
      modo_demo: false,
    });

    const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
    const { rerender } = render(
      <QueryClientProvider client={client}>
        <AsistenteDrawer abierto onCerrar={vi.fn()} claveActor="hija:1" nombreActor="Luna" nivel="exploradoras" />
      </QueryClientProvider>,
    );

    await user.click(screen.getByRole('button', { name: '¿Qué misiones me faltan hoy?' }));
    expect(await screen.findByText('Respuesta para Luna')).toBeInTheDocument();

    rerender(
      <QueryClientProvider client={client}>
        <AsistenteDrawer abierto onCerrar={vi.fn()} claveActor="hija:3" nombreActor="Estrella" nivel="pro" />
      </QueryClientProvider>,
    );

    expect(screen.queryByText('Respuesta para Luna')).not.toBeInTheDocument();
    expect(screen.getByText(/Hola Estrella/i)).toBeInTheDocument();
  });
});
