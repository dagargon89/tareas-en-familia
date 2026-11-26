import { useState, type FormEvent } from 'react';
import type { Configuracion as ConfiguracionDatos, ConfiguracionEntrada } from '../../api/ApiClient.ts';
import { useConfiguracion, useGuardarConfiguracion } from '../../hooks/useMetasConfig.ts';
import { detalleError } from '../../lib/errores.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';
import { SkeletonLista } from '../../ui/Cargando.tsx';
import { EstadoError } from '../../ui/EstadoError.tsx';
import { Toast } from '../../ui/Toast.tsx';
import { PadresFamilia } from './PadresFamilia.tsx';
import { validarAjustes, type AjustesBorrador } from './validarJuego.ts';

interface VistaProps {
  config: ConfiguracionDatos;
  enviando: boolean;
  aviso: string | null;
  onCerrarAviso?: () => void;
  erroresServidor?: Record<string, string>;
  onGuardar: (c: ConfiguracionEntrada) => void;
}

/** Ajustes de la familia (RF-18). El PIN que se cambia es el del padre de esta sesión. */
export function ConfiguracionVista({ config, enviando, aviso, onCerrarAviso, erroresServidor = {}, onGuardar }: VistaProps) {
  const [a, setA] = useState<AjustesBorrador>({ racha_umbral: config.racha_umbral, sonidos_default: config.sonidos_default, pin: '', confirmar: '' });
  const [tocado, setTocado] = useState(false);
  const errores = validarAjustes(a);
  const ver = (k: keyof AjustesBorrador) => (tocado ? errores[k] : undefined) ?? erroresServidor[k === 'pin' ? 'pin_padre' : k];

  function enviar(e: FormEvent) {
    e.preventDefault();
    setTocado(true);
    if (Object.keys(errores).length > 0) return;
    onGuardar({ racha_umbral: a.racha_umbral, sonidos_default: a.sonidos_default, ...(a.pin ? { pin_padre: a.pin } : {}) });
  }

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-6">
      <h1 className="m-0 text-display">Ajustes</h1>
      <Toast mensaje={aviso} tono="menta" onCerrar={onCerrarAviso} />

      <fieldset className="m-0 flex flex-col gap-3 rounded-tarjeta border-0 bg-superficie p-5">
        <legend className="sr-only">Racha</legend>
        <h2 className="m-0 text-titulo">Racha semanal</h2>
        <Campo
          etiqueta="Porcentaje de tareas aprobadas para que una semana cuente"
          type="number"
          min={1}
          max={100}
          value={Number.isNaN(a.racha_umbral) ? '' : a.racha_umbral}
          onChange={(e) => setA({ ...a, racha_umbral: e.target.valueAsNumber })}
          error={ver('racha_umbral')}
          ayuda="Aplica desde el siguiente cierre semanal (lunes). Una semana no cumplida nunca resta puntos."
        />
      </fieldset>

      <fieldset className="m-0 flex flex-col gap-3 rounded-tarjeta border-0 bg-superficie p-5">
        <legend className="sr-only">Sonidos</legend>
        <h2 className="m-0 text-titulo">Sonidos</h2>
        <label className="flex min-h-tactil items-center gap-3 text-cuerpo">
          <input type="checkbox" className="size-6 accent-[var(--tf-indigo-600)]" checked={a.sonidos_default} onChange={(e) => setA({ ...a, sonidos_default: e.target.checked })} />
          Activados al crear un perfil nuevo
        </label>
        <p className="m-0 text-etiqueta text-tinta-600">Cada hija puede encenderlos o apagarlos en su perfil.</p>
      </fieldset>

      <fieldset className="m-0 flex flex-col gap-3 rounded-tarjeta border-0 bg-superficie p-5">
        <legend className="sr-only">PIN de modo padre</legend>
        <h2 className="m-0 text-titulo">Mi PIN de modo padre</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <Campo etiqueta="PIN nuevo" inputMode="numeric" autoComplete="off" maxLength={4} value={a.pin} onChange={(e) => setA({ ...a, pin: e.target.value.replace(/\D/g, '') })} error={ver('pin')} />
          <Campo etiqueta="Repite el PIN" inputMode="numeric" autoComplete="off" maxLength={4} value={a.confirmar} onChange={(e) => setA({ ...a, confirmar: e.target.value.replace(/\D/g, '') })} error={ver('confirmar')} />
        </div>
        <p className="m-0 text-etiqueta text-tinta-600">Déjalo vacío para no cambiarlo. Solo cambia tu PIN, no el del otro padre.</p>
      </fieldset>

      <dl className="m-0 grid gap-2 text-etiqueta text-tinta-600 sm:grid-cols-3">
        <div>
          <dt>Días que se preparan por adelantado</dt>
          <dd className="m-0 text-cuerpo text-tinta-900">{config.dias_generacion}</dd>
        </div>
        <div>
          <dt>Días que se guardan las fotos</dt>
          <dd className="m-0 text-cuerpo text-tinta-900">{config.evidencia_dias_retencion}</dd>
        </div>
        <div>
          <dt>Puntos de tareas que crean ellas</dt>
          <dd className="m-0 text-cuerpo text-tinta-900">{config.puntos_tarea_propia}</dd>
        </div>
      </dl>

      <div className="flex justify-end">
        <Boton type="submit" disabled={enviando}>
          {enviando ? 'Guardando…' : 'Guardar ajustes'}
        </Boton>
      </div>
    </form>
  );
}

export function Configuracion() {
  const config = useConfiguracion();
  const guardar = useGuardarConfiguracion();
  const [aviso, setAviso] = useState<string | null>(null);
  // Al guardar con éxito se remonta la vista: el PIN escrito se borra y se toman los valores nuevos.
  const [version, setVersion] = useState(0);

  if (config.isPending) return <SkeletonLista filas={3} texto="Cargando ajustes…" />;
  if (config.isError) return <EstadoError mensaje="No se pudieron cargar los ajustes. Intenta de nuevo." onReintentar={() => void config.refetch()} />;

  const d = guardar.error ? detalleError(guardar.error) : null;
  return (
    <div className="flex flex-col gap-8">
      <ConfiguracionVista
        key={version}
        config={config.data}
        enviando={guardar.isPending}
        aviso={aviso}
        onCerrarAviso={() => setAviso(null)}
        erroresServidor={d?.campos ?? {}}
        onGuardar={(c) =>
          guardar.mutate(c, {
            onSuccess: () => {
              setAviso(c.pin_padre ? 'Ajustes guardados. Tu PIN cambió.' : 'Ajustes guardados.');
              setVersion((n) => n + 1);
            },
            onError: (e) => setAviso(detalleError(e).campos ? null : 'No se pudo guardar. Intenta otra vez.'),
          })
        }
      />
      {/* Formulario aparte: alta del otro padre (RF-02, ADR-009). */}
      <PadresFamilia />
    </div>
  );
}
