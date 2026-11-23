import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { Instancia, Panel, PerfilPublico, Semana } from '../../../src/api/ApiClient.ts';
import { WeekCalendar } from '../../../src/features/calendario/WeekCalendar.tsx';
import { FormTareaPro } from '../../../src/features/mis-tareas/FormTareaPro.tsx';
import { validarRutina } from '../../../src/features/mis-tareas/rutinaPropia.ts';
import { PanelVista } from '../../../src/features/padre/Panel.tsx';
import { lunesDe, sumarDias } from '../../../src/lib/fechas.ts';
import { ESCRITORIO } from '../../../src/lib/useMediaQuery.ts';

const perfil = (id: number, apodo: string): PerfilPublico => ({ id, apodo, avatar: 'gato', color: 'menta', nivel_ux: 'aventureras', sonidos: true, tema: 'claro', bloqueado_hasta: null });
const inst = (id: number, fecha: string, extra: Partial<Instancia> = {}): Instancia => ({
  id, tarea_id: id, perfil_id: 1, fecha, hora: null, fecha_entrega: null, titulo: `Tarea ${id}`, icono: 'cama', color: 'ambar', tipo: 'casa',
  materia: null, puntos: 3, estado: 'pendiente', requiere_foto: false, tiene_evidencia: false, evidencia_id: null, mensaje_devolucion: null, completada_en: null, ...extra,
});

describe('lunesDe', () => {
  it('devuelve el lunes de la semana ISO (domingo pertenece a la semana anterior)', () => {
    expect(lunesDe('2026-11-23')).toBe('2026-11-23'); // lunes
    expect(lunesDe('2026-11-25')).toBe('2026-11-23'); // miércoles
    expect(lunesDe('2026-11-22')).toBe('2026-11-16'); // domingo
  });
});

describe('WeekCalendar (RF-14)', () => {
  const desde = '2026-11-23';
  const semana: Semana = {
    desde,
    hasta: sumarDias(desde, 6),
    dias: Array.from({ length: 7 }, (_, n) => {
      const fecha = sumarDias(desde, n);
      const instancias = n === 1 ? [inst(1, fecha, { hora: '18:00', tipo: 'actividad', titulo: 'Natación', estado: 'aprobada' }), inst(2, fecha, { perfil_id: 2 })] : [];
      return { fecha, es_hoy: n === 2, instancias, resumen: { total: instancias.length, hechas: 0, aprobadas: 0 } };
    }),
  };

  const original = window.matchMedia;
  const conEscritorio = (amplio: boolean) => {
    window.matchMedia = (q: string) => ({
      matches: amplio && q === ESCRITORIO, media: q, onchange: null,
      addEventListener: vi.fn(), removeEventListener: vi.fn(), addListener: vi.fn(), removeListener: vi.fn(), dispatchEvent: vi.fn(),
    });
  };
  afterEach(() => {
    window.matchMedia = original;
  });

  it('en celular y tablet abre hoy y muestra la agenda del día que se elija', async () => {
    conEscritorio(false);
    render(<WeekCalendar semana={semana} hoy={sumarDias(desde, 2)} onSemana={vi.fn()} />);
    const dias = within(screen.getByRole('group', { name: 'Días de la semana' })).getAllByRole('button');
    expect(dias).toHaveLength(7);
    expect(dias[2]).toHaveAttribute('aria-pressed', 'true');
    expect(dias[2]).toHaveAttribute('aria-current', 'date');
    expect(screen.getByText('Día libre')).toBeInTheDocument();

    await userEvent.click(dias[1]);
    expect(dias[1]).toHaveAccessibleName('mar 24 , 1 de 2 listas');
    expect(screen.getByRole('heading', { level: 2, name: 'martes, 24 de noviembre' })).toBeInTheDocument();
    expect(screen.getByLabelText('Natación a las 18:00, aprobada')).toBeInTheDocument();
    expect(screen.queryByText('Día libre')).toBeNull();
  });

  it('en modo padre agrupa el día por hija y dice de quién es cada tarea', async () => {
    conEscritorio(false);
    render(<WeekCalendar semana={semana} hoy={sumarDias(desde, 1)} perfiles={[perfil(1, 'Luna'), perfil(2, 'Sol'), perfil(3, 'Estrella')]} onSemana={vi.fn()} />);
    expect(screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent)).toEqual(['Luna· 1', 'Sol· 1']);
    expect(screen.getByLabelText('Tarea 2, Sol, pendiente')).toBeInTheDocument();
  });

  it('en escritorio es un tablero con una fila por hija y una columna por día', () => {
    conEscritorio(true);
    render(<WeekCalendar semana={semana} hoy={desde} perfiles={[perfil(1, 'Luna'), perfil(2, 'Sol'), perfil(3, 'Estrella')]} onSemana={vi.fn()} />);
    const tabla = screen.getByRole('table');
    expect(within(tabla).getAllByRole('columnheader')).toHaveLength(7);
    expect(within(tabla).getAllByRole('rowheader').map((h) => h.textContent)).toEqual(['Luna', 'Sol']);
    expect(document.querySelector('th[aria-current="date"]')?.textContent).toBe('lun · hoy23');
    expect(screen.getByLabelText('Natación a las 18:00, Luna, aprobada')).toBeInTheDocument();
    expect(screen.getAllByText('Libre')).toHaveLength(12);
  });

  it('en escritorio, la vista de la hija es un solo carril sin encabezado', () => {
    conEscritorio(true);
    render(<WeekCalendar semana={semana} hoy={desde} onSemana={vi.fn()} />);
    expect(screen.queryAllByRole('rowheader')).toHaveLength(0);
    expect(screen.getAllByText('Libre')).toHaveLength(6);
  });

  it('respeta los límites de navegación', async () => {
    const onSemana = vi.fn();
    render(<WeekCalendar semana={semana} hoy={desde} onSemana={onSemana} puedeAdelante={false} />);
    expect(screen.getByRole('button', { name: 'Semana siguiente' })).toBeDisabled();
    await userEvent.click(screen.getByRole('button', { name: 'Semana anterior' }));
    expect(onSemana).toHaveBeenCalledWith(-1);
  });
});

describe('FormTareaPro (RF-15)', () => {
  it('una tarea de casa diaria no envía puntos, perfil ni categoría', async () => {
    const onGuardar = vi.fn();
    render(<FormTareaPro tarea={null} hoy="2026-11-23" enviando={false} onGuardar={onGuardar} onCancelar={vi.fn()} />);
    await userEvent.click(screen.getByRole('button', { name: 'Casa o hábito' }));
    await userEvent.type(screen.getByLabelText('Tarea'), 'Practicar guitarra');
    await userEvent.click(screen.getByRole('button', { name: 'Agregar tarea' }));
    const enviado = onGuardar.mock.calls[0][0];
    expect(enviado).toMatchObject({ titulo: 'Practicar guitarra', tipo: 'casa', materia: null, fecha_entrega: null, recurrencia: { frecuencia: 'diaria', fecha_inicio: '2026-11-23' } });
    for (const campo of ['puntos', 'perfil_id', 'categoria_id', 'creada_por']) expect(enviado).not.toHaveProperty(campo);
  });

  it('por defecto ofrece el formulario escolar', () => {
    render(<FormTareaPro tarea={null} hoy="2026-11-23" enviando={false} onGuardar={vi.fn()} onCancelar={vi.fn()} />);
    expect(screen.getByLabelText('¿Qué te dejaron?')).toBeInTheDocument();
  });

  it('semanal exige días', () => {
    expect(validarRutina({ tipo: 'actividad', titulo: 'Piano', icono: 'musica', frecuencia: 'semanal', dias_semana: [], hora: '', fecha_inicio: '2026-11-23', fecha_fin: '' }).dias_semana).toBeDefined();
  });
});

describe('PanelVista (RF-16)', () => {
  const panel: Panel = {
    semana_iso: '2026-W48',
    hijas: [
      { perfil: perfil(1, 'Luna'), porcentaje_semana: 40, por_aprobar: 2, racha_semanas: 0, disponible: 30, proximas_entregas: [] },
      { perfil: perfil(2, 'Sol'), porcentaje_semana: 90, por_aprobar: 1, racha_semanas: 3, disponible: 80, proximas_entregas: [inst(5, '2026-11-23', { titulo: 'Maqueta', materia: 'Ciencias', fecha_entrega: '2026-11-27' })] },
    ],
    meta_familiar: null,
    canjes_pendientes: [],
  };

  it('suma lo pendiente por aprobar y no reordena a las hijas por desempeño (RN-02)', () => {
    render(<PanelVista panel={panel} perfiles={[]} />, { wrapper: MemoryRouter });
    expect(screen.getByRole('link', { name: /3 por aprobar/ })).toHaveAttribute('href', '/padre/aprobaciones');
    expect(screen.getAllByRole('heading', { level: 2 }).map((h) => h.textContent)).toEqual(['Luna', 'Sol']);
    expect(screen.getByText('Maqueta')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Crear una meta familiar' })).toBeInTheDocument();
  });
});
