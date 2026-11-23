import { useState, type FormEvent } from 'react';
import type { Tarea, TareaPropiaEntrada } from '../../api/ApiClient.ts';
import { FONDO_COLOR } from '../../lib/avatares.ts';
import { fechaCorta, sumarDias } from '../../lib/fechas.ts';
import { iconoDe } from '../../lib/iconos.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';
import { ICONOS_ESCOLARES, MATERIAS_SUGERIDAS, aEntradaEscolar, borradorEscolarDe, validarEscolar, type BorradorEscolar } from './escolarPropia.ts';

interface Props {
  tarea: Tarea | null;
  hoy: string;
  /** Puntos de la tarea si ya se conocen (al editar); la hija no lee la configuración. */
  puntos?: number;
  enviando: boolean;
  erroresServidor?: Record<string, string>;
  onGuardar: (datos: TareaPropiaEntrada) => void;
  onCancelar: () => void;
}

const chip = (activo: boolean) =>
  `min-h-tactil rounded-boton px-4 text-etiqueta ${activo ? 'bg-indigo-600 text-blanco' : 'bg-superficie text-tinta-900'}`;

/** Formulario de tarea escolar propia (RF-19), pensado para 4° de primaria en adelante. */
export function FormEscolarPropia({ tarea, hoy, puntos, enviando, erroresServidor = {}, onGuardar, onCancelar }: Props) {
  const [b, setB] = useState<BorradorEscolar>(() => borradorEscolarDe(tarea));
  const [tocado, setTocado] = useState(false);
  const [otraFecha, setOtraFecha] = useState(false);
  const errores = validarEscolar(b, hoy);
  const ver = (k: keyof BorradorEscolar) => (tocado ? errores[k] : undefined) ?? erroresServidor[k];
  const cambiar = <K extends keyof BorradorEscolar>(k: K, v: BorradorEscolar[K]) => setB((x) => ({ ...x, [k]: v }));

  const manana = sumarDias(hoy, 1);
  const pasado = sumarDias(hoy, 2);
  const fechaRapida = b.fecha_entrega === manana || b.fecha_entrega === pasado;

  function enviar(e: FormEvent) {
    e.preventDefault();
    setTocado(true);
    if (Object.keys(errores).length > 0) return;
    onGuardar(aEntradaEscolar(b, hoy));
  }

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-6">
      <Campo etiqueta="¿Qué te dejaron?" placeholder="Ej. Resolver la página 45" maxLength={80} value={b.titulo} onChange={(e) => cambiar('titulo', e.target.value)} error={ver('titulo')} />

      <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
        <legend className="mb-2 text-etiqueta">Materia</legend>
        <div className="flex flex-wrap gap-2">
          {MATERIAS_SUGERIDAS.map((m) => (
            <button key={m} type="button" aria-pressed={b.materia === m} className={chip(b.materia === m)} onClick={() => cambiar('materia', m)}>
              {m}
            </button>
          ))}
        </div>
        <Campo etiqueta="Otra materia" maxLength={40} value={MATERIAS_SUGERIDAS.includes(b.materia as (typeof MATERIAS_SUGERIDAS)[number]) ? '' : b.materia} onChange={(e) => cambiar('materia', e.target.value)} error={ver('materia')} />
      </fieldset>

      <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
        <legend className="mb-2 text-etiqueta">¿Para cuándo es?</legend>
        <div className="flex flex-wrap gap-2">
          <button type="button" aria-pressed={b.fecha_entrega === manana} className={chip(b.fecha_entrega === manana)} onClick={() => { setOtraFecha(false); cambiar('fecha_entrega', manana); }}>
            Mañana
          </button>
          <button type="button" aria-pressed={b.fecha_entrega === pasado} className={chip(b.fecha_entrega === pasado)} onClick={() => { setOtraFecha(false); cambiar('fecha_entrega', pasado); }}>
            Pasado mañana
          </button>
          <button type="button" aria-pressed={otraFecha || (!!b.fecha_entrega && !fechaRapida)} className={chip(otraFecha || (!!b.fecha_entrega && !fechaRapida))} onClick={() => setOtraFecha(true)}>
            {b.fecha_entrega && !fechaRapida ? fechaCorta(b.fecha_entrega) : 'Otro día'}
          </button>
        </div>
        {(otraFecha || (!!b.fecha_entrega && !fechaRapida)) && (
          <Campo etiqueta="Fecha de entrega" type="date" min={hoy} value={b.fecha_entrega} onChange={(e) => cambiar('fecha_entrega', e.target.value)} />
        )}
        {ver('fecha_entrega') && <span className="text-etiqueta text-indigo-700">{ver('fecha_entrega')}</span>}
      </fieldset>

      <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
        <legend className="mb-2 text-etiqueta">Ícono</legend>
        <div className="grid grid-cols-5 gap-2 sm:grid-cols-10">
          {ICONOS_ESCOLARES.map((clave) => {
            const I = iconoDe(clave);
            return (
              <button key={clave} type="button" aria-label={clave} aria-pressed={b.icono === clave} onClick={() => cambiar('icono', clave)} className={`flex aspect-square items-center justify-center rounded-icono ${b.icono === clave ? FONDO_COLOR.indigo : 'bg-superficie text-tinta-900'}`}>
                <I className="size-1/2" aria-hidden="true" />
              </button>
            );
          })}
        </div>
      </fieldset>

      <Campo etiqueta="¿A qué hora la vas a hacer? (opcional)" type="time" value={b.hora} onChange={(e) => cambiar('hora', e.target.value)} />

      <p className="m-0 rounded-tarjeta-interna bg-lavanda-100 p-4 text-cuerpo text-indigo-700">
        {puntos !== undefined ? `Vale ${puntos} puntos. ` : 'Te da puntos. '}
        Papá o mamá la ven en su lista y te los dan cuando aprueben que la hiciste.
      </p>

      <div className="flex flex-wrap justify-end gap-3">
        <Boton variante="suave" onClick={onCancelar}>
          Cancelar
        </Boton>
        <Boton type="submit" disabled={enviando}>
          {enviando ? 'Guardando…' : tarea ? 'Guardar cambios' : 'Agregar tarea'}
        </Boton>
      </div>
    </form>
  );
}
