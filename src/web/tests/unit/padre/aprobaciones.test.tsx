import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { InstanciaRevision, Progreso, ResultadoRevision } from '../../../src/api/ApiClient.ts';
import { AprobacionesVista } from '../../../src/features/padre/Aprobaciones.tsx';
import { DevolverForm } from '../../../src/features/padre/DevolverForm.tsx';
import { resumirResultados } from '../../../src/features/padre/resumirResultados.ts';
import { MisLogrosVista } from '../../../src/features/progreso/MisLogros.tsx';

const perfil = { id: 2, apodo: 'Sofi', avatar: 'gato', color: 'menta', nivel_ux: 'aventureras', sonidos: true, tema: 'claro', bloqueado_hasta: null } as const;
const item = (id: number, extra: Partial<InstanciaRevision> = {}): InstanciaRevision => ({
  id, tarea_id: id, perfil_id: 2, fecha: '2026-11-23', hora: null, fecha_entrega: null, titulo: `Tarea ${id}`, icono: 'cama',
  color: 'ambar', tipo: 'casa', materia: null, puntos: 3, estado: 'hecha', requiere_foto: false, tiene_evidencia: false,
  evidencia_id: null, mensaje_devolucion: null, completada_en: '2026-11-23T18:00:00-07:00', perfil, ...extra,
});

describe('DevolverForm (RF-07, CP-047 en frontend)', () => {
  it('no permite devolver sin mensaje', async () => {
    const onDevolver = vi.fn();
    render(<DevolverForm item={item(1)} enviando={false} onDevolver={onDevolver} onCancelar={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Devolver' })).toBeDisabled();
    await userEvent.type(screen.getByLabelText(/qué le dices a sofi/i), '   ');
    expect(screen.getByRole('button', { name: 'Devolver' })).toBeDisabled();
  });

  it('envía el mensaje recortado; las sugerencias lo llenan', async () => {
    const onDevolver = vi.fn();
    render(<DevolverForm item={item(1)} enviando={false} onDevolver={onDevolver} onCancelar={vi.fn()} />);
    await userEvent.click(screen.getByRole('button', { name: 'Falta la foto' }));
    await userEvent.click(screen.getByRole('button', { name: 'Devolver' }));
    expect(onDevolver).toHaveBeenCalledWith('Falta la foto');
  });

  it('limita el mensaje a 140 caracteres', () => {
    render(<DevolverForm item={item(1)} enviando={false} onDevolver={vi.fn()} onCancelar={vi.fn()} />);
    expect(screen.getByLabelText(/qué le dices a sofi/i)).toHaveAttribute('maxLength', '140');
  });
});

describe('AprobacionesVista', () => {
  const base = {
    seleccion: [] as number[], devolviendo: null, ocupado: false, aviso: null, urlFoto: (id: number) => `/api/v1/evidencias/${id}`,
    onSeleccionar: vi.fn(), onSeleccionarTodas: vi.fn(), onAprobar: vi.fn(), onAprobarSeleccion: vi.fn(), onAbrirDevolver: vi.fn(), onDevolver: vi.fn(),
  };

  it('bandeja vacía muestra un mensaje tranquilo', () => {
    render(<AprobacionesVista {...base} items={[]} />);
    expect(screen.getByText('No hay nada por aprobar. ¡Todo al día!')).toBeInTheDocument();
  });

  it('aprobar en lote solo se habilita con selección y muestra cuántas', () => {
    const { rerender } = render(<AprobacionesVista {...base} items={[item(1), item(2)]} />);
    expect(screen.getByRole('button', { name: /aprobar\s+seleccionadas/i })).toBeDisabled();
    rerender(<AprobacionesVista {...base} items={[item(1), item(2)]} seleccion={[1, 2]} />);
    expect(screen.getByRole('button', { name: /aprobar 2 seleccionadas/i })).toBeEnabled();
  });

  it('muestra la miniatura de la foto cuando hay evidencia', () => {
    render(<AprobacionesVista {...base} items={[item(1, { evidencia_id: 9, tiene_evidencia: true })]} />);
    expect(screen.getByRole('img', { name: 'Foto de Tarea 1' })).toHaveAttribute('src', '/api/v1/evidencias/9');
  });
});

describe('resumirResultados', () => {
  it('suma puntos por hija y menciona insignias y meta', () => {
    const items = [item(1), item(2), item(3, { perfil: { ...perfil, id: 1, apodo: 'Luna' } })];
    const r = (id: number, pts: number, extra: Partial<ResultadoRevision> = {}): ResultadoRevision => ({
      instancia: { ...item(id), estado: 'aprobada' }, puntos_acreditados: pts, insignias_nuevas: [], meta_cumplida: false, ...extra,
    });
    const texto = resumirResultados(
      [r(1, 3), r(2, 5), r(3, 4, { insignias_nuevas: [{ codigo: 'primera', nombre: 'Primera misión', descripcion: '', icono: 'brillo', color: 'ambar', obtenida_en: 'x' }], meta_cumplida: true })],
      items,
    );
    expect(texto).toBe('Aprobado: +8 a Sofi, +4 a Luna. Nuevas insignias: Primera misión. ¡Cumplieron la meta familiar!');
  });
});

describe('MisLogrosVista (RF-08)', () => {
  const progreso: Progreso = {
    xp: 120, saldo: 60, disponible: 40, nivel: { nombre: 'Brote', xp_desde: 100, xp_hasta: 299 }, siguiente_nivel: { nombre: 'Raíz', xp_desde: 300, xp_hasta: 699 },
    racha_semanas: 0, semana_actual: { asignadas: 10, aprobadas: 4, porcentaje: 40 }, insignias: [], meta_familiar: null,
  };

  it('muestra nivel, lo que falta, disponible y lo apartado en premios', () => {
    render(<MisLogrosVista progreso={progreso} nivel="aventureras" perfiles={[]} />);
    expect(screen.getByText('Brote')).toBeInTheDocument();
    expect(screen.getByText('Te faltan 180 XP para Raíz')).toBeInTheDocument();
    expect(screen.getByText('40')).toBeInTheDocument();
    expect(screen.getByText('Tienes 20 XP apartados en premios que pediste.')).toBeInTheDocument();
  });

  it('racha en cero invita a empezar, sin regañar (RN-01)', () => {
    render(<MisLogrosVista progreso={progreso} nivel="exploradoras" perfiles={[]} />);
    expect(screen.getByText('¡Empieza tu racha!')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Mis insignias' })).toBeInTheDocument();
  });
});
