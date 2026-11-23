import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { Tarea } from '../../../src/api/ApiClient.ts';
import { FormEscolarPropia } from '../../../src/features/mis-tareas/FormEscolarPropia.tsx';
import { aEntradaEscolar, puedeCrearTareas, validarEscolar } from '../../../src/features/mis-tareas/escolarPropia.ts';
import { TareasVista } from '../../../src/features/padre/Tareas.tsx';
import { sumarDias } from '../../../src/lib/fechas.ts';

const HOY = '2026-11-23';

describe('puedeCrearTareas (RF-19)', () => {
  it('Aventureras y Pro sí; Exploradoras no', () => {
    expect(puedeCrearTareas('aventureras')).toBe(true);
    expect(puedeCrearTareas('pro')).toBe(true);
    expect(puedeCrearTareas('exploradoras')).toBe(false);
  });
});

describe('CP-039 · Formulario de escolar propia', () => {
  it('exige título, materia y entrega no anterior a hoy', () => {
    const e = validarEscolar({ titulo: ' ', materia: '', fecha_entrega: '2026-11-22', icono: 'libro', hora: '', fecha_inicio: '' }, HOY);
    expect(Object.keys(e).sort()).toEqual(['fecha_entrega', 'materia', 'titulo']);
  });

  it('solo envía campos de TareaPropiaEntrada, siempre escolar y de una vez desde hoy', async () => {
    const onGuardar = vi.fn();
    render(<FormEscolarPropia tarea={null} hoy={HOY} enviando={false} onGuardar={onGuardar} onCancelar={vi.fn()} />);
    await userEvent.type(screen.getByLabelText('¿Qué te dejaron?'), 'Página 45');
    await userEvent.click(screen.getByRole('button', { name: 'Matemáticas' }));
    await userEvent.click(screen.getByRole('button', { name: 'Mañana' }));
    await userEvent.click(screen.getByRole('button', { name: 'Agregar tarea' }));

    expect(onGuardar).toHaveBeenCalledTimes(1);
    const enviado = onGuardar.mock.calls[0][0];
    expect(enviado).toEqual({
      titulo: 'Página 45',
      icono: 'libro',
      tipo: 'escolar',
      materia: 'Matemáticas',
      fecha_entrega: sumarDias(HOY, 1),
      requiere_foto: false,
      recurrencia: { frecuencia: 'una_vez', dias_semana: null, hora: null, fecha_inicio: HOY, fecha_fin: null },
    });
    // Nunca manda puntos, perfil ni creada_por: los fija el servidor (RF-19).
    expect(enviado).not.toHaveProperty('puntos');
    expect(enviado).not.toHaveProperty('perfil_id');
    expect(enviado).not.toHaveProperty('creada_por');
  });

  it('no envía si falta la fecha y lo dice con palabras amables', async () => {
    const onGuardar = vi.fn();
    render(<FormEscolarPropia tarea={null} hoy={HOY} enviando={false} onGuardar={onGuardar} onCancelar={vi.fn()} />);
    await userEvent.type(screen.getByLabelText('¿Qué te dejaron?'), 'Leer');
    await userEvent.click(screen.getByRole('button', { name: 'Español' }));
    await userEvent.click(screen.getByRole('button', { name: 'Agregar tarea' }));
    expect(onGuardar).not.toHaveBeenCalled();
    expect(screen.getByText('¿Para cuándo es?', { selector: 'span' })).toBeInTheDocument();
  });

  it('al editar conserva la fecha de inicio original', () => {
    const t = aEntradaEscolar({ titulo: 'X', materia: 'Arte', fecha_entrega: '2026-11-30', icono: 'arte', hora: '16:00', fecha_inicio: '2026-11-20' }, HOY);
    expect(t.recurrencia.fecha_inicio).toBe('2026-11-20');
    expect(t.recurrencia.hora).toBe('16:00');
  });
});

describe('Tareas del padre · filtro "Creadas por ellas"', () => {
  const rec = { frecuencia: 'una_vez', dias_semana: null, hora: null, fecha_inicio: HOY, fecha_fin: null } as const;
  const tareas: Tarea[] = [
    { id: 1, perfil_id: 2, categoria_id: 1, titulo: 'Tender la cama', icono: 'cama', puntos: 3, tipo: 'casa', materia: null, fecha_entrega: null, requiere_foto: false, creada_por: 'padre', activa: true, recurrencia: { ...rec, frecuencia: 'diaria' } },
    { id: 2, perfil_id: 2, categoria_id: 2, titulo: 'Maqueta', icono: 'ciencia', puntos: 5, tipo: 'escolar', materia: 'Ciencias', fecha_entrega: '2026-11-27', requiere_foto: false, creada_por: 'perfil', activa: true, recurrencia: rec },
  ];
  const perfiles = [{ id: 2, apodo: 'Sofi', avatar: 'gato', color: 'menta', nivel_ux: 'aventureras', sonidos: true, tema: 'claro', bloqueado_hasta: null }] as const;
  const props = { tareas, perfiles: [...perfiles], perfilId: null, verInactivas: false, colorDeCategoria: () => 'menta' as const, onFiltrar: vi.fn(), onVerInactivas: vi.fn(), onNueva: vi.fn(), onEditar: vi.fn(), onDesactivar: vi.fn() };

  it('marca las creadas por la hija y cuenta cuántas hay', () => {
    render(<TareasVista {...props} soloDeEllas={false} onSoloDeEllas={vi.fn()} />);
    expect(screen.getByText('Creada por Sofi')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /creadas por ellas \(1\)/i })).toBeInTheDocument();
  });

  it('con el filtro activo solo muestra las suyas', () => {
    render(<TareasVista {...props} soloDeEllas onSoloDeEllas={vi.fn()} />);
    expect(screen.getByText('Maqueta')).toBeInTheDocument();
    expect(screen.queryByText('Tender la cama')).not.toBeInTheDocument();
  });
});
