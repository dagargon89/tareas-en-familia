import { UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { UsuarioAdmin, UsuarioEntrada } from '../../api/ApiClient.ts';
import { useCrearUsuario, useUsuarios } from '../../hooks/useUsuarios.ts';
import { detalleError } from '../../lib/errores.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';
import { Cargando } from '../../ui/Cargando.tsx';
import { PADRE_VACIO, aEntradaPadre, validarPadre, type ErroresPadre, type PadreBorrador } from './validarPadre.ts';

/** Máximo de padres por familia (doc 03, ADR-009). */
export const MAX_PADRES = 2;

interface VistaProps {
  padres: UsuarioAdmin[];
  enviando: boolean;
  aviso: string | null;
  erroresServidor?: Record<string, string>;
  onCrear: (u: UsuarioEntrada) => void;
}

/**
 * Padres de la familia (RF-02, ADR-009): lista los padres y, si hay lugar, da de alta al otro con
 * su correo, una contraseña inicial y su PIN de modo padre.
 */
export function PadresFamiliaVista({ padres, enviando, aviso, erroresServidor = {}, onCrear }: VistaProps) {
  const [d, setD] = useState<PadreBorrador>(PADRE_VACIO);
  const [tocado, setTocado] = useState(false);
  const errores = validarPadre(d);
  const ver = (k: keyof ErroresPadre) => (tocado ? errores[k] : undefined) ?? erroresServidor[k];
  const cambiar = (k: keyof PadreBorrador, v: string) => setD((x) => ({ ...x, [k]: v }));
  const hayLugar = padres.length < MAX_PADRES;

  function enviar(e: FormEvent) {
    e.preventDefault();
    setTocado(true);
    if (Object.keys(errores).length > 0) return;
    onCrear(aEntradaPadre(d));
  }

  return (
    <section aria-labelledby="titulo-padres" className="flex flex-col gap-4 rounded-tarjeta bg-superficie p-5">
      <h2 id="titulo-padres" className="m-0 text-titulo">
        Padres de la familia
      </h2>
      <ul className="m-0 flex list-none flex-col gap-2 p-0">
        {padres.map((p) => (
          <li key={p.id} className="flex items-center gap-3 rounded-tarjeta-interna bg-blanco p-3">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-lavanda-100 text-indigo-700" aria-hidden="true">
              <UserRound className="size-5" />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block text-cuerpo">
                {p.nombre}
                {p.es_actual && <span className="text-etiqueta text-tinta-600"> · tú</span>}
              </span>
              <span className="block break-all text-etiqueta text-tinta-600">{p.email}</span>
            </span>
          </li>
        ))}
      </ul>
      <p aria-live="polite" className="m-0 empty:hidden rounded-tarjeta-interna bg-menta-200 p-3 text-cuerpo">
        {aviso}
      </p>

      {hayLugar ? (
        <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
          <h3 className="m-0 text-cuerpo font-semibold">Agregar al otro padre</h3>
          <p className="m-0 text-etiqueta text-tinta-600">
            Entrará con su correo y esta contraseña en cualquier dispositivo, y usará su propio PIN para el modo padre. Compártele los datos en persona; podrá cambiar su PIN en Ajustes.
          </p>
          <div className="grid gap-4 sm:grid-cols-2">
            <Campo etiqueta="Nombre" autoComplete="off" maxLength={60} value={d.nombre} onChange={(e) => cambiar('nombre', e.target.value)} error={ver('nombre')} />
            <Campo etiqueta="Correo" type="email" autoComplete="off" value={d.email} onChange={(e) => cambiar('email', e.target.value)} error={ver('email')} />
            <Campo etiqueta="Contraseña" type="password" autoComplete="new-password" value={d.password} onChange={(e) => cambiar('password', e.target.value)} error={ver('password')} ayuda="Mínimo 10 caracteres." />
            <Campo etiqueta="Repite la contraseña" type="password" autoComplete="new-password" value={d.confirmar} onChange={(e) => cambiar('confirmar', e.target.value)} error={ver('confirmar')} />
            <Campo
              etiqueta="Su PIN de modo padre"
              inputMode="numeric"
              autoComplete="off"
              maxLength={4}
              value={d.pin}
              onChange={(e) => cambiar('pin', e.target.value.replace(/\D/g, ''))}
              error={ver('pin')}
              ayuda="4 dígitos, sin repetidos ni secuencias."
            />
            <Campo
              etiqueta="Repite su PIN"
              inputMode="numeric"
              autoComplete="off"
              maxLength={4}
              value={d.confirmarPin}
              onChange={(e) => cambiar('confirmarPin', e.target.value.replace(/\D/g, ''))}
              error={ver('confirmarPin')}
            />
          </div>
          <div className="flex justify-end">
            <Boton type="submit" disabled={enviando}>
              {enviando ? 'Agregando…' : 'Agregar padre'}
            </Boton>
          </div>
        </form>
      ) : (
        <p className="m-0 text-etiqueta text-tinta-600">La familia ya tiene {MAX_PADRES} padres, el máximo.</p>
      )}
    </section>
  );
}

export function PadresFamilia() {
  const padres = useUsuarios();
  const crear = useCrearUsuario();
  const [aviso, setAviso] = useState<string | null>(null);
  // Tras un alta exitosa se remonta la vista para limpiar el formulario (y las contraseñas escritas).
  const [version, setVersion] = useState(0);

  if (padres.isPending) return <Cargando />;
  if (padres.isError) return <p className="text-cuerpo text-indigo-700">No se pudo cargar la lista de padres. Intenta de nuevo.</p>;

  const d = crear.error ? detalleError(crear.error) : null;
  return (
    <PadresFamiliaVista
      key={version}
      padres={padres.data}
      enviando={crear.isPending}
      aviso={aviso ?? (d && !d.campos ? d.mensaje : null)}
      erroresServidor={d?.campos ?? {}}
      onCrear={(u) =>
        crear.mutate(u, {
          onSuccess: (nuevo) => {
            setAviso(`Listo: ${nuevo.nombre} ya puede entrar con ${nuevo.email}.`);
            setVersion((n) => n + 1);
            crear.reset();
          },
          onError: () => setAviso(null),
        })
      }
    />
  );
}
