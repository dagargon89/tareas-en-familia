import { EyeOff, LockOpen, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { PerfilAdmin, PerfilEntrada } from '../../api/ApiClient.ts';
import { useBorrarPerfil, useDesbloquearPerfil, useGuardarPerfil, usePerfilesAdmin } from '../../hooks/usePerfilesAdmin.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { detalleError } from '../../lib/errores.ts';
import { minutosRestantes } from '../../lib/fechas.ts';
import { NOMBRE_NIVEL } from '../../niveles/nivel.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Cargando } from '../../ui/Cargando.tsx';
import { FormPerfil } from './FormPerfil.tsx';

interface VistaProps {
  perfiles: PerfilAdmin[];
  onNuevo: () => void;
  onEditar: (p: PerfilAdmin) => void;
  onDesbloquear: (p: PerfilAdmin) => void;
  onDesactivar: (p: PerfilAdmin) => void;
  onBorrar: (p: PerfilAdmin) => void;
}

/** Lista de perfiles en modo padre (RF-17). */
export function PerfilesVista({ perfiles, onNuevo, onEditar, onDesbloquear, onDesactivar, onBorrar }: VistaProps) {
  const accion = 'inline-flex min-h-tactil items-center gap-1 rounded-boton px-3 text-etiqueta';
  return (
    <section className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-4">
        <h1 className="m-0 text-display">Perfiles</h1>
        <Boton icono={<Plus className="size-5" aria-hidden="true" />} onClick={onNuevo}>
          Nuevo
        </Boton>
      </div>
      <ul className="m-0 flex list-none flex-col gap-3 p-0">
        {perfiles.map((p) => {
          const Avatar = avatarDe(p.avatar);
          const bloqueo = p.bloqueado_hasta ? minutosRestantes(p.bloqueado_hasta) : 0;
          return (
            <li key={p.id} className={`flex flex-wrap items-center gap-4 rounded-tarjeta bg-superficie p-4 ${p.activo ? '' : 'opacity-60'}`}>
              <span className={`flex size-12 items-center justify-center rounded-full ${FONDO_COLOR[p.color]}`}>
                <Avatar className="size-6" aria-hidden="true" />
              </span>
              <div className="flex min-w-0 flex-1 flex-col">
                <span className="text-titulo">{p.apodo}</span>
                <span className="text-etiqueta text-tinta-600">
                  {NOMBRE_NIVEL[p.nivel_ux]}
                  {!p.activo && ' · desactivado'}
                  {bloqueo > 0 && ` · bloqueado ${bloqueo} min`}
                </span>
              </div>
              <div className="flex flex-wrap gap-1">
                {bloqueo > 0 && (
                  <button type="button" className={`${accion} bg-lavanda-100 text-indigo-700`} onClick={() => onDesbloquear(p)}>
                    <LockOpen className="size-4" aria-hidden="true" /> Desbloquear
                  </button>
                )}
                <button type="button" className={`${accion} text-indigo-700 hover:bg-lavanda-100`} onClick={() => onEditar(p)}>
                  <Pencil className="size-4" aria-hidden="true" /> Editar
                </button>
                {p.activo && (
                  <button type="button" className={`${accion} text-tinta-600 hover:bg-tarjeta`} onClick={() => onDesactivar(p)}>
                    <EyeOff className="size-4" aria-hidden="true" /> Desactivar
                  </button>
                )}
                <button type="button" className={`${accion} text-tinta-600 hover:bg-tarjeta`} onClick={() => onBorrar(p)} aria-label={`Borrar definitivamente a ${p.apodo}`}>
                  <Trash2 className="size-4" aria-hidden="true" />
                </button>
              </div>
            </li>
          );
        })}
      </ul>
    </section>
  );
}

export function Perfiles() {
  const perfiles = usePerfilesAdmin();
  const guardar = useGuardarPerfil();
  const desbloquear = useDesbloquearPerfil();
  const borrar = useBorrarPerfil();
  const [editando, setEditando] = useState<PerfilAdmin | null | 'nuevo'>(null);

  if (perfiles.isPending) return <Cargando />;
  if (perfiles.isError) return <p className="text-cuerpo text-indigo-700">{detalleError(perfiles.error).mensaje}</p>;

  if (editando !== null) {
    const perfil = editando === 'nuevo' ? null : editando;
    const errores = guardar.error ? (detalleError(guardar.error).campos ?? {}) : {};
    return (
      <section className="flex flex-col gap-6">
        <h1 className="m-0 text-display">{perfil ? `Editar a ${perfil.apodo}` : 'Nuevo perfil'}</h1>
        <FormPerfil
          key={perfil?.id ?? 'nuevo'}
          perfil={perfil}
          enviando={guardar.isPending}
          erroresServidor={errores}
          onCancelar={() => {
            guardar.reset();
            setEditando(null);
          }}
          onGuardar={(datos: PerfilEntrada) =>
            guardar.mutate({ id: perfil?.id ?? null, datos }, { onSuccess: () => setEditando(null) })
          }
        />
      </section>
    );
  }

  return (
    <PerfilesVista
      perfiles={perfiles.data}
      onNuevo={() => setEditando('nuevo')}
      onEditar={(p) => setEditando(p)}
      onDesbloquear={(p) => desbloquear.mutate(p.id)}
      onDesactivar={(p) => {
        if (window.confirm(`¿Desactivar a ${p.apodo}? Se oculta del selector y conserva su historial.`)) {
          borrar.mutate({ id: p.id, definitivo: false });
        }
      }}
      onBorrar={(p) => {
        const texto = window.prompt(
          `Esto borra para siempre a ${p.apodo}: tareas, puntos, insignias y fotos. No se puede deshacer.\n\nEscribe ${p.apodo} para confirmar.`,
        );
        if (texto === p.apodo) borrar.mutate({ id: p.id, definitivo: true });
      }}
    />
  );
}
