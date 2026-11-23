import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { Instancia, RecompensaParaPerfil } from '../../src/api/ApiClient.ts';
import { PinPad } from '../../src/features/acceso/PinPad.tsx';
import { TaskCard } from '../../src/features/mi-dia/TaskCard.tsx';
import { RewardCard } from '../../src/features/premios/RewardCard.tsx';
import { XpWaveBar } from '../../src/features/progreso/XpWaveBar.tsx';

const instancia: Instancia = {
  id: 1, tarea_id: 1, perfil_id: 1, fecha: '2026-11-23', hora: '07:30', fecha_entrega: null,
  titulo: 'Tender la cama', icono: 'cama', color: 'ambar', tipo: 'casa', materia: null, puntos: 3,
  estado: 'pendiente', requiere_foto: false, tiene_evidencia: false, evidencia_id: null,
  mensaje_devolucion: null, completada_en: null,
};

describe('TaskCard', () => {
  it('llama onListo al tocar Listo en una pendiente', async () => {
    const onListo = vi.fn();
    render(<TaskCard instancia={instancia} nivel="aventureras" puedeMarcar onListo={onListo} />);
    await userEvent.click(screen.getByRole('button', { name: /listo/i }));
    expect(onListo).toHaveBeenCalledWith(instancia, expect.objectContaining({ x: expect.any(Number) }));
  });

  it('no muestra Listo en un día futuro (RF-05)', () => {
    render(<TaskCard instancia={instancia} nivel="aventureras" puedeMarcar={false} onListo={vi.fn()} />);
    expect(screen.queryByRole('button', { name: /listo/i })).not.toBeInTheDocument();
  });

  it('muestra el mensaje de devolución con el vocabulario del nivel', () => {
    const devuelta = { ...instancia, estado: 'devuelta' as const, mensaje_devolucion: 'falta la almohada' };
    const { rerender } = render(<TaskCard instancia={devuelta} nivel="exploradoras" puedeMarcar onListo={vi.fn()} />);
    expect(screen.getByText('Casi. Papá dice: falta la almohada')).toBeInTheDocument();
    rerender(<TaskCard instancia={devuelta} nivel="pro" puedeMarcar onListo={vi.fn()} />);
    expect(screen.getByText('Devuelta: falta la almohada')).toBeInTheDocument();
  });

  it('en espera de aprobación no ofrece Listo', () => {
    render(<TaskCard instancia={{ ...instancia, estado: 'hecha' }} nivel="pro" puedeMarcar onListo={vi.fn()} />);
    expect(screen.getByText('Listo. Pendiente de aprobación')).toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});

describe('RewardCard (CP-064)', () => {
  const base: RecompensaParaPerfil = { id: 1, titulo: 'Helado', icono: 'helado', costo: 80, activa: true, alcanza: true, faltan: 0 };

  it('muestra "Ya te alcanza" y Canjear solo cuando alcanza', () => {
    render(<RewardCard recompensa={base} nivel="aventureras" onCanjear={vi.fn()} />);
    expect(screen.getByText('¡Ya te alcanza!')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Canjear' })).toBeInTheDocument();
  });

  it('muestra cuánto falta cuando no alcanza', () => {
    render(<RewardCard recompensa={{ ...base, alcanza: false, faltan: 22 }} nivel="aventureras" onCanjear={vi.fn()} />);
    expect(screen.queryByText('¡Ya te alcanza!')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Canjear' })).not.toBeInTheDocument();
    expect(screen.getByText('Te faltan 22 XP')).toBeInTheDocument();
  });
});

describe('PinPad', () => {
  it('entrega el PIN al completar 4 dígitos', async () => {
    const onCompleto = vi.fn();
    render(<PinPad titulo="Tu PIN" onCompleto={onCompleto} />);
    for (const d of ['2', '5', '8', '0']) await userEvent.click(screen.getByRole('button', { name: d }));
    expect(onCompleto).toHaveBeenCalledWith('2580');
  });

  it('bloqueado no acepta dígitos y muestra el mensaje amable', async () => {
    const onCompleto = vi.fn();
    render(<PinPad titulo="Tu PIN" onCompleto={onCompleto} bloqueado="Descansa un ratito" />);
    expect(screen.getByRole('button', { name: '1' })).toBeDisabled();
    expect(screen.getByText('Descansa un ratito')).toBeInTheDocument();
  });
});

describe('XpWaveBar', () => {
  it('calcula cuánto falta para el siguiente nivel', () => {
    render(
      <XpWaveBar xp={612} nivel={{ nombre: 'Raíz', xp_desde: 300, xp_hasta: 699 }} siguiente={{ nombre: 'Árbol', xp_desde: 700, xp_hasta: 1499 }} nivelUx="aventureras" />,
    );
    expect(screen.getByText('Te faltan 88 XP para Árbol')).toBeInTheDocument();
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '78');
  });
});
