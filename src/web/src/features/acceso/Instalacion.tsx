import { useState, type FormEvent } from 'react';
import { Navigate, useNavigate } from 'react-router';
import type { InstalacionEntrada } from '../../api/ApiClient.ts';
import { useEstadoInstalacion, useInstalar } from '../../hooks/useSesion.ts';
import { detalleError } from '../../lib/errores.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';
import { Cargando } from '../../ui/Cargando.tsx';
import { FormasVivas } from '../../ui/FormasVivas.tsx';
import { validarInstalacion, type ErroresInstalacion } from './validarInstalacion.ts';

interface VistaProps {
  requiereToken: boolean;
  enviando: boolean;
  error: string | null;
  erroresServidor?: Record<string, string>;
  onInstalar: (datos: InstalacionEntrada) => void;
}

/** Vista "Configura la app" (RF-20): crea al primer padre cuando no hay usuarios. */
export function InstalacionVista({ requiereToken, enviando, error, erroresServidor = {}, onInstalar }: VistaProps) {
  const [d, setD] = useState({ nombre: '', email: '', password: '', confirmar: '', pin: '', token: '' });
  const [tocado, setTocado] = useState(false);
  const errores = validarInstalacion(d, requiereToken);
  const ver = (k: keyof ErroresInstalacion) => (tocado ? errores[k] : undefined) ?? erroresServidor[k];
  const cambiar = (k: keyof typeof d, v: string) => setD((x) => ({ ...x, [k]: v }));

  function enviar(e: FormEvent) {
    e.preventDefault();
    setTocado(true);
    if (Object.keys(errores).length > 0) return;
    onInstalar({
      nombre: d.nombre.trim(),
      email: d.email.trim(),
      password: d.password,
      pin: d.pin,
      ...(requiereToken ? { token: d.token.trim() } : {}),
    });
  }

  return (
    <main className="relative mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 p-6">
      <FormasVivas />
      <header className="flex flex-col gap-2">
        <h1 className="m-0 text-display">Configura la app</h1>
        <p className="m-0 text-cuerpo text-tinta-600">
          Todavía no hay ninguna cuenta. Crea la tuya de papá o mamá; después podrás agregar los perfiles de tus hijas.
        </p>
      </header>
      <form onSubmit={enviar} noValidate className="flex flex-col gap-4 rounded-tarjeta bg-superficie p-6">
        <Campo etiqueta="Tu nombre" autoComplete="name" maxLength={60} value={d.nombre} onChange={(e) => cambiar('nombre', e.target.value)} error={ver('nombre')} />
        <Campo etiqueta="Correo" type="email" autoComplete="username" value={d.email} onChange={(e) => cambiar('email', e.target.value)} error={ver('email')} />
        <Campo etiqueta="Contraseña" type="password" autoComplete="new-password" value={d.password} onChange={(e) => cambiar('password', e.target.value)} error={ver('password')} ayuda="Mínimo 10 caracteres." />
        <Campo etiqueta="Repite la contraseña" type="password" autoComplete="new-password" value={d.confirmar} onChange={(e) => cambiar('confirmar', e.target.value)} error={ver('confirmar')} />
        <Campo
          etiqueta="PIN de modo padre"
          inputMode="numeric"
          autoComplete="off"
          maxLength={4}
          value={d.pin}
          onChange={(e) => cambiar('pin', e.target.value.replace(/\D/g, ''))}
          error={ver('pin')}
          ayuda="4 dígitos, sin repetidos ni secuencias. Distinto al de tus hijas."
        />
        {requiereToken && (
          <Campo
            etiqueta="Token de instalación"
            autoComplete="off"
            value={d.token}
            onChange={(e) => cambiar('token', e.target.value)}
            error={ver('token')}
            ayuda="Está en el archivo .env del servidor (instalacion.token)."
          />
        )}
        <p aria-live="polite" className="m-0 min-h-[1.5em] text-etiqueta text-indigo-700">
          {error}
        </p>
        <Boton type="submit" anchoCompleto disabled={enviando}>
          {enviando ? 'Creando…' : 'Crear mi cuenta'}
        </Boton>
      </form>
    </main>
  );
}

const MENSAJES: Record<string, string> = {
  ESTADO_INVALIDO: 'La app ya tiene una cuenta. Entra con tu correo y contraseña.',
  PROHIBIDO: 'El token de instalación no es correcto.',
  DEMASIADOS_INTENTOS: 'Demasiados intentos. Espera un minuto.',
};

export function Instalacion() {
  const navegar = useNavigate();
  const estado = useEstadoInstalacion();
  const instalar = useInstalar();

  if (estado.isPending) return <Cargando />;
  if (!estado.data?.requerida) return <Navigate to="/login" replace />;

  const d = instalar.error ? detalleError(instalar.error) : null;
  return (
    <InstalacionVista
      requiereToken={estado.data.requiere_token}
      enviando={instalar.isPending}
      error={d && !d.campos ? (MENSAJES[d.codigo] ?? d.mensaje) : null}
      erroresServidor={d?.campos ?? {}}
      onInstalar={(datos) =>
        instalar.mutate(datos, {
          // Lo siguiente es crear los perfiles, que pide modo padre.
          onSuccess: () => navegar('/modo-padre', { replace: true, state: { volverA: '/padre/perfiles' } }),
        })
      }
    />
  );
}
