import { useState, type FormEvent } from 'react';
import { Navigate, useNavigate } from 'react-router';
import { useEstadoInstalacion, useLogin, useSesion } from '../../hooks/useSesion.ts';
import { detalleError } from '../../lib/errores.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';
import { FormasVivas } from '../../ui/FormasVivas.tsx';

interface VistaProps {
  onEntrar: (email: string, password: string) => void;
  enviando: boolean;
  error: string | null;
}

/** Vista del login del padre (RF-02). No hay registro: el padre se crea por consola. */
export function LoginPadreVista({ onEntrar, enviando, error }: VistaProps) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  function enviar(e: FormEvent) {
    e.preventDefault();
    onEntrar(email.trim(), password);
  }

  return (
    <main className="relative mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-8 p-6">
      <FormasVivas />
      <header className="flex flex-col gap-2">
        <h1 className="m-0 text-display">Tareas en Familia</h1>
        <p className="m-0 text-cuerpo text-tinta-600">Papá o mamá: entren una sola vez en este dispositivo.</p>
        {/* ADR-003: la cuenta del padre abre el dispositivo; las hijas entran con avatar y PIN. */}
        <ul className="m-0 flex list-disc flex-col gap-1 pl-5 text-etiqueta text-tinta-600">
          <li>Después, sus hijas abren la app con su avatar y su PIN, sin su contraseña.</li>
          <li>La sesión se renueva sola mientras se use; solo vence si pasan 30 días sin abrir la app.</li>
          <li>En iPhone, si instalan la app en la pantalla de inicio, entren una vez también desde ahí.</li>
        </ul>
      </header>
      <form onSubmit={enviar} className="flex flex-col gap-4 rounded-tarjeta bg-superficie p-6" noValidate>
        <Campo etiqueta="Correo" type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required />
        <Campo etiqueta="Contraseña" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        <p aria-live="polite" className="m-0 min-h-[1.5em] text-etiqueta text-indigo-700">
          {error}
        </p>
        <Boton type="submit" anchoCompleto disabled={enviando || !email || !password}>
          {enviando ? 'Entrando…' : 'Entrar'}
        </Boton>
      </form>
    </main>
  );
}

const MENSAJES: Record<string, string> = {
  CREDENCIALES: 'Correo o contraseña incorrectos.',
  DEMASIADOS_INTENTOS: 'Demasiados intentos. Espera un minuto e intenta de nuevo.',
};

export function LoginPadre() {
  const navegar = useNavigate();
  const { data } = useSesion();
  const login = useLogin();
  const instalacion = useEstadoInstalacion();

  if (data?.usuario) return <Navigate to="/" replace />;
  // RF-20: sin ningún usuario en la base, primero se configura la app.
  if (instalacion.data?.requerida) return <Navigate to="/instalacion" replace />;

  const error = login.error ? (MENSAJES[detalleError(login.error).codigo] ?? detalleError(login.error).mensaje) : null;
  return (
    <LoginPadreVista
      enviando={login.isPending}
      error={error}
      onEntrar={(email, password) => login.mutate({ email, password }, { onSuccess: () => navegar('/', { replace: true }) })}
    />
  );
}
