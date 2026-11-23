import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { UsuarioAdmin } from '../../../src/api/ApiClient.ts';
import { PadresFamiliaVista } from '../../../src/features/padre/PadresFamilia.tsx';
import { validarPadre, validarPassword, PADRE_VACIO, PASSWORD_VACIO } from '../../../src/features/padre/validarPadre.ts';

const papa: UsuarioAdmin = { id: 1, nombre: 'Papá Demo', email: 'demo@tareas.local', es_actual: true };
const mama: UsuarioAdmin = { id: 2, nombre: 'Mamá', email: 'mama@tareas.local', es_actual: false };

async function llenar(campos: Record<string, string>) {
  for (const [etiqueta, valor] of Object.entries(campos)) await userEvent.type(screen.getByLabelText(etiqueta), valor);
}

describe('PadresFamilia (RF-02, ADR-009)', () => {
  it('lista los padres y marca al de la sesión', () => {
    render(<PadresFamiliaVista padres={[papa]} enviando={false} aviso={null} onCrear={vi.fn()} onCambiarPassword={vi.fn()} />);
    expect(screen.getByRole('heading', { name: 'Padres de la familia' })).toBeInTheDocument();
    expect(screen.getByText('demo@tareas.local')).toBeInTheDocument();
    expect(screen.getByText('· tú')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Agregar padre' })).toBeInTheDocument();
  });

  it('no envía si faltan datos y muestra qué corregir', async () => {
    const onCrear = vi.fn();
    render(<PadresFamiliaVista padres={[papa]} enviando={false} aviso={null} onCrear={onCrear} onCambiarPassword={vi.fn()} />);
    await userEvent.click(screen.getByRole('button', { name: 'Agregar padre' }));
    expect(onCrear).not.toHaveBeenCalled();
    expect(screen.getByText('Escribe su nombre')).toBeInTheDocument();
    expect(screen.getByText('Mínimo 10 caracteres')).toBeInTheDocument();
  });

  it('envía nombre, correo, contraseña y PIN (sin las confirmaciones)', async () => {
    const onCrear = vi.fn();
    render(<PadresFamiliaVista padres={[papa]} enviando={false} aviso={null} onCrear={onCrear} onCambiarPassword={vi.fn()} />);
    await llenar({
      Nombre: '  Mamá ', Correo: 'mama@tareas.local', Contraseña: 'OtraContrasena2026', 'Repite la contraseña': 'OtraContrasena2026',
      'Su PIN de modo padre': '7351', 'Repite su PIN': '7351',
    });
    await userEvent.click(screen.getByRole('button', { name: 'Agregar padre' }));
    expect(onCrear).toHaveBeenCalledWith({ nombre: 'Mamá', email: 'mama@tareas.local', password: 'OtraContrasena2026', pin: '7351' });
  });

  it('con 2 padres ya no ofrece el formulario', () => {
    render(<PadresFamiliaVista padres={[papa, mama]} enviando={false} aviso={null} onCrear={vi.fn()} onCambiarPassword={vi.fn()} />);
    expect(screen.queryByRole('button', { name: 'Agregar padre' })).toBeNull();
    expect(screen.getByText('La familia ya tiene 2 padres, el máximo.')).toBeInTheDocument();
  });

  it('muestra el error del servidor en su campo', () => {
    render(<PadresFamiliaVista padres={[papa]} enviando={false} aviso={null} erroresServidor={{ email: 'Ese correo ya existe en la familia.' }} onCrear={vi.fn()} onCambiarPassword={vi.fn()} />);
    expect(screen.getByText('Ese correo ya existe en la familia.')).toBeInTheDocument();
  });

  it('validarPadre exige PIN no trivial y que coincida', () => {
    const base = { ...PADRE_VACIO, nombre: 'Mamá', email: 'mama@tareas.local', password: 'OtraContrasena2026', confirmar: 'OtraContrasena2026' };
    expect(validarPadre({ ...base, pin: '1234', confirmarPin: '1234' }).pin).toBeDefined();
    expect(validarPadre({ ...base, pin: '7351', confirmarPin: '7352' }).confirmarPin).toBe('Los PIN no coinciden');
    expect(validarPadre({ ...base, pin: '7351', confirmarPin: '7351' })).toEqual({});
  });

  it('validarPassword pide la actual solo para la propia', () => {
    const d = { ...PASSWORD_VACIO, nueva: 'NuevaContrasena2026', confirmar: 'NuevaContrasena2026' };
    expect(validarPassword(d, true).actual).toBeDefined();
    expect(validarPassword(d, false)).toEqual({});
    expect(validarPassword({ ...d, confirmar: 'otra' }, false).confirmar).toBe('Las contraseñas no coinciden');
  });

  it('el padre de la sesión cambia su contraseña escribiendo la actual (ADR-010)', async () => {
    const onCambiarPassword = vi.fn();
    render(<PadresFamiliaVista padres={[papa, mama]} enviando={false} aviso={null} onCrear={vi.fn()} onCambiarPassword={onCambiarPassword} />);
    await userEvent.click(screen.getByRole('button', { name: 'Cambiar mi contraseña' }));
    const form = screen.getByRole('form', { name: 'Cambiar mi contraseña' });
    await userEvent.click(within(form).getByRole('button', { name: 'Guardar contraseña' }));
    expect(onCambiarPassword).not.toHaveBeenCalled();
    expect(within(form).getByText('Escribe tu contraseña actual')).toBeInTheDocument();

    await userEvent.type(within(form).getByLabelText('Contraseña actual'), 'FamiliaDemo2026');
    await userEvent.type(within(form).getByLabelText('Contraseña nueva'), 'NuevaContrasena2026');
    await userEvent.type(within(form).getByLabelText('Repite la contraseña nueva'), 'NuevaContrasena2026');
    await userEvent.click(within(form).getByRole('button', { name: 'Guardar contraseña' }));
    expect(onCambiarPassword).toHaveBeenCalledWith(papa, { password_actual: 'FamiliaDemo2026', password: 'NuevaContrasena2026' });
  });

  it('restablece la del otro padre sin pedir la actual', async () => {
    const onCambiarPassword = vi.fn();
    render(<PadresFamiliaVista padres={[papa, mama]} enviando={false} aviso={null} onCrear={vi.fn()} onCambiarPassword={onCambiarPassword} />);
    await userEvent.click(screen.getByRole('button', { name: 'Restablecer contraseña' }));
    const form = screen.getByRole('form', { name: 'Restablecer la contraseña de Mamá' });
    expect(within(form).queryByLabelText('Contraseña actual')).toBeNull();
    await userEvent.type(within(form).getByLabelText('Contraseña nueva'), 'OtraNueva20261');
    await userEvent.type(within(form).getByLabelText('Repite la contraseña nueva'), 'OtraNueva20261');
    await userEvent.click(within(form).getByRole('button', { name: 'Guardar contraseña' }));
    expect(onCambiarPassword).toHaveBeenCalledWith(mama, { password: 'OtraNueva20261' });
  });

  it('muestra el error del servidor y Cancelar cierra el formulario', async () => {
    render(<PadresFamiliaVista padres={[papa]} enviando={false} aviso={null} onCrear={vi.fn()} erroresPassword={{ password_actual: 'La contraseña actual no es correcta.' }} onCambiarPassword={vi.fn()} />);
    await userEvent.click(screen.getByRole('button', { name: 'Cambiar mi contraseña' }));
    expect(screen.getByText('La contraseña actual no es correcta.')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }));
    expect(screen.queryByRole('form', { name: 'Cambiar mi contraseña' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Cambiar mi contraseña' })).toBeInTheDocument();
  });
});
