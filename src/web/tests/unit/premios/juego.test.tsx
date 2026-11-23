import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { describe, expect, it, vi } from 'vitest';
import type { Canje, PerfilPublico, Recompensa, RecompensaParaPerfil } from '../../../src/api/ApiClient.ts';
import { HijaLayoutVista } from '../../../src/features/hija/HijaLayout.tsx';
import { ConfiguracionVista } from '../../../src/features/padre/Configuracion.tsx';
import { RecompensasVista } from '../../../src/features/padre/Recompensas.tsx';
import { validarAjustes, validarMeta, validarRecompensa } from '../../../src/features/padre/validarJuego.ts';
import { PremiosVista } from '../../../src/features/premios/Premios.tsx';

const perfil: PerfilPublico = { id: 2, apodo: 'Sofi', avatar: 'gato', color: 'menta', nivel_ux: 'aventureras', sonidos: true, tema: 'claro', bloqueado_hasta: null };
const rec = (id: number, costo: number, alcanza: boolean): RecompensaParaPerfil => ({ id, titulo: `Premio ${id}`, icono: 'regalo', costo, activa: true, alcanza, faltan: alcanza ? 0 : costo - 40 });
const recompensa = (id: number): Recompensa => ({ id, titulo: `Premio ${id}`, icono: 'regalo', costo: 50, activa: true });
const canje = (id: number, estado: Canje['estado']): Canje => ({ id, perfil_id: 2, recompensa: recompensa(id), costo: 50, estado, created_at: '2026-11-23T17:00:00-07:00' });

describe('validaciones del S4', () => {
  it('recompensa: título y costo de 1 a 9999', () => {
    expect(validarRecompensa({ titulo: 'Helado', icono: 'helado', costo: 80, activa: true })).toEqual({});
    expect(Object.keys(validarRecompensa({ titulo: ' ', icono: 'helado', costo: 0, activa: true })).sort()).toEqual(['costo', 'titulo']);
    expect(validarRecompensa({ titulo: 'x', icono: 'helado', costo: 10000, activa: true }).costo).toBeDefined();
  });

  it('meta: límite no antes del inicio', () => {
    const base = { titulo: 'Pizza', premio: 'Noche de pizza', objetivo_puntos: 300, fecha_inicio: '2026-11-23', fecha_limite: null };
    expect(validarMeta(base)).toEqual({});
    expect(validarMeta({ ...base, fecha_limite: '2026-11-20' }).fecha_limite).toBeDefined();
    expect(validarMeta({ ...base, objetivo_puntos: 0 }).objetivo_puntos).toBeDefined();
  });

  it('ajustes: umbral 1 a 100 y PIN solo si se escribe (CP-086 en frontend)', () => {
    expect(validarAjustes({ racha_umbral: 60, sonidos_default: true, pin: '', confirmar: '' })).toEqual({});
    expect(validarAjustes({ racha_umbral: 0, sonidos_default: true, pin: '', confirmar: '' }).racha_umbral).toBeDefined();
    expect(validarAjustes({ racha_umbral: 80, sonidos_default: true, pin: '1111', confirmar: '1111' }).pin).toBeDefined();
    expect(validarAjustes({ racha_umbral: 80, sonidos_default: true, pin: '2580', confirmar: '2581' }).confirmar).toBeDefined();
  });
});

describe('PremiosVista (RF-09)', () => {
  const props = { disponible: 40, nivel: 'aventureras' as const, pidiendo: null, aviso: null, onCanjear: vi.fn() };

  it('muestra primero lo que ya alcanza', () => {
    render(<PremiosVista {...props} recompensas={[rec(1, 150, false), rec(2, 40, true)]} canjes={[]} />);
    const titulos = screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent);
    expect(titulos).toEqual(['Premio 2', 'Premio 1']);
  });

  it('un premio con canje pendiente no se puede volver a pedir', () => {
    render(<PremiosVista {...props} recompensas={[rec(2, 40, true)]} canjes={[canje(2, 'solicitado')]} />);
    expect(screen.queryByRole('button', { name: 'Canjear' })).not.toBeInTheDocument();
    expect(screen.getByText('Pedido. Papá lo está revisando')).toBeInTheDocument();
  });

  it('un canje rechazado se explica sin regañar y avisa que los puntos regresaron', () => {
    render(<PremiosVista {...props} recompensas={[]} canjes={[canje(3, 'rechazado')]} />);
    expect(screen.getByText('Esta vez no. Tus puntos regresaron')).toBeInTheDocument();
  });
});

describe('RecompensasVista (padre)', () => {
  const props = { recompensas: [recompensa(9)], perfiles: [perfil], ocupado: false, aviso: null, editando: null, guardando: false, onEditar: vi.fn(), onGuardar: vi.fn() };

  it('solicitado: aprobar o "ahora no"; aprobado: marcar entregado', async () => {
    const onRevisar = vi.fn();
    render(<RecompensasVista {...props} canjes={[canje(1, 'solicitado'), canje(2, 'aprobado')]} onRevisar={onRevisar} />);
    const porRevisar = screen.getByText('Premio 1').closest('li')!;
    await userEvent.click(within(porRevisar).getByRole('button', { name: /aprobar/i }));
    expect(onRevisar).toHaveBeenLastCalledWith(expect.objectContaining({ id: 1 }), 'aprobado');
    await userEvent.click(within(porRevisar).getByRole('button', { name: /ahora no/i }));
    expect(onRevisar).toHaveBeenLastCalledWith(expect.objectContaining({ id: 1 }), 'rechazado');
    await userEvent.click(screen.getByRole('button', { name: /ya lo entregué/i }));
    expect(onRevisar).toHaveBeenLastCalledWith(expect.objectContaining({ id: 2 }), 'entregado');
  });
});

describe('ConfiguracionVista (RF-18)', () => {
  const config = { racha_umbral: 80, sonidos_default: true, dias_generacion: 7, evidencia_dias_retencion: 30, puntos_tarea_propia: 5 };

  it('sin PIN escrito no envía pin_padre', async () => {
    const onGuardar = vi.fn();
    render(<ConfiguracionVista config={config} enviando={false} aviso={null} onGuardar={onGuardar} />);
    const umbral = screen.getByLabelText(/porcentaje de tareas aprobadas/i);
    await userEvent.clear(umbral);
    await userEvent.type(umbral, '60');
    await userEvent.click(screen.getByRole('button', { name: 'Guardar ajustes' }));
    expect(onGuardar).toHaveBeenCalledWith({ racha_umbral: 60, sonidos_default: true });
  });

  it('con PIN válido y confirmado envía pin_padre', async () => {
    const onGuardar = vi.fn();
    render(<ConfiguracionVista config={config} enviando={false} aviso={null} onGuardar={onGuardar} />);
    await userEvent.type(screen.getByLabelText('PIN nuevo'), '2580');
    await userEvent.type(screen.getByLabelText('Repite el PIN'), '2580');
    await userEvent.click(screen.getByRole('button', { name: 'Guardar ajustes' }));
    expect(onGuardar).toHaveBeenCalledWith(expect.objectContaining({ pin_padre: '2580' }));
  });
});

describe('HijaLayoutVista · preferencias (E-22)', () => {
  it('apaga los sonidos y cambia el tema en Aventureras', async () => {
    const onSonidos = vi.fn();
    const onTema = vi.fn();
    render(<HijaLayoutVista perfil={perfil} onCambiar={vi.fn()} onSonidos={onSonidos} onTema={onTema} />, { wrapper: MemoryRouter });
    await userEvent.click(screen.getByRole('button', { name: 'Apagar sonidos' }));
    expect(onSonidos).toHaveBeenCalledWith(false);
    await userEvent.click(screen.getByRole('button', { name: /cambiar tema/i }));
    expect(onTema).toHaveBeenCalledWith('oscuro');
  });

  it('Exploradoras no tienen botón de tema (sin modo oscuro)', () => {
    render(<HijaLayoutVista perfil={{ ...perfil, nivel_ux: 'exploradoras' }} onCambiar={vi.fn()} onSonidos={vi.fn()} onTema={vi.fn()} />, { wrapper: MemoryRouter });
    expect(screen.queryByRole('button', { name: /cambiar tema/i })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Apagar sonidos' })).toBeInTheDocument();
  });
});
