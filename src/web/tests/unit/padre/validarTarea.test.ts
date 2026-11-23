import { describe, expect, it } from 'vitest';
import { aEntrada, validarTarea, type BorradorTarea } from '../../../src/features/padre/validarTarea.ts';

const HOY = '2026-11-23';
const base: BorradorTarea = {
  perfil_id: 1, categoria_id: 1, titulo: 'Tender la cama', icono: 'cama', puntos: 3, tipo: 'casa',
  materia: '', fecha_entrega: '', requiere_foto: false, frecuencia: 'diaria', dias_semana: [],
  hora: '', fecha_inicio: HOY, fecha_fin: '',
};

describe('validarTarea (RF-03, RF-13)', () => {
  it('acepta una tarea diaria válida', () => {
    expect(validarTarea(base, HOY)).toEqual({});
  });

  it('puntos: 1 a 100 en casa y escolar; 0 permitido solo en actividad (CP-026)', () => {
    expect(validarTarea({ ...base, puntos: 0 }, HOY).puntos).toBeDefined();
    expect(validarTarea({ ...base, puntos: 101 }, HOY).puntos).toBeDefined();
    expect(validarTarea({ ...base, tipo: 'actividad', puntos: 0 }, HOY).puntos).toBeUndefined();
    expect(validarTarea({ ...base, tipo: 'actividad', puntos: 101 }, HOY).puntos).toBeDefined();
  });

  it('título obligatorio y de máximo 80', () => {
    expect(validarTarea({ ...base, titulo: '   ' }, HOY).titulo).toBeDefined();
    expect(validarTarea({ ...base, titulo: 'x'.repeat(81) }, HOY).titulo).toBeDefined();
  });

  it('semanal exige al menos un día', () => {
    expect(validarTarea({ ...base, frecuencia: 'semanal' }, HOY).dias_semana).toBeDefined();
    expect(validarTarea({ ...base, frecuencia: 'semanal', dias_semana: ['lu'] }, HOY).dias_semana).toBeUndefined();
  });

  it('escolar exige materia y entrega no anterior a hoy', () => {
    const e = validarTarea({ ...base, tipo: 'escolar' }, HOY);
    expect(e.materia).toBeDefined();
    expect(e.fecha_entrega).toBeDefined();
    expect(validarTarea({ ...base, tipo: 'escolar', materia: 'Español', fecha_entrega: '2026-11-22' }, HOY).fecha_entrega).toBeDefined();
    expect(validarTarea({ ...base, tipo: 'escolar', materia: 'Español', fecha_entrega: '2026-11-26' }, HOY)).toEqual({});
  });
});

describe('aEntrada', () => {
  it('escolar nueva se convierte en una_vez desde hoy, con materia y entrega', () => {
    const t = aEntrada({ ...base, tipo: 'escolar', materia: ' Español ', fecha_entrega: '2026-11-26', frecuencia: 'semanal', dias_semana: ['lu'], fecha_inicio: '' }, HOY);
    expect(t.materia).toBe('Español');
    expect(t.fecha_entrega).toBe('2026-11-26');
    expect(t.recurrencia).toEqual({ frecuencia: 'una_vez', dias_semana: null, hora: null, fecha_inicio: HOY, fecha_fin: null });
  });

  it('casa no envía materia ni entrega aunque el borrador las tenga', () => {
    const t = aEntrada({ ...base, materia: 'Mate', fecha_entrega: '2026-12-01' }, HOY);
    expect(t.materia).toBeNull();
    expect(t.fecha_entrega).toBeNull();
  });

  it('semanal ordena los días de lunes a domingo; una_vez no lleva fecha_fin', () => {
    expect(aEntrada({ ...base, frecuencia: 'semanal', dias_semana: ['vi', 'lu', 'mi'] }, HOY).recurrencia.dias_semana).toEqual(['lu', 'mi', 'vi']);
    expect(aEntrada({ ...base, frecuencia: 'una_vez', fecha_fin: '2026-12-31' }, HOY).recurrencia.fecha_fin).toBeNull();
  });

  it('nunca incluye campos fuera de TareaEntrada', () => {
    expect(Object.keys(aEntrada(base, HOY)).sort()).toEqual(
      ['categoria_id', 'fecha_entrega', 'icono', 'materia', 'perfil_id', 'puntos', 'recurrencia', 'requiere_foto', 'tipo', 'titulo'].sort(),
    );
  });
});
