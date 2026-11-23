import { useState, type FormEvent, type ReactNode } from 'react';
import type { DiaSemana, Frecuencia, Tarea, TareaPropiaEntrada } from '../../api/ApiClient.ts';
import { FONDO_COLOR } from '../../lib/avatares.ts';
import { ICONOS } from '../../lib/iconos.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';
import { FormEscolarPropia } from './FormEscolarPropia.tsx';
import { aEntradaRutina, borradorRutinaDe, validarRutina, type BorradorRutina } from './rutinaPropia.ts';

type Tipo = 'escolar' | 'casa' | 'actividad';
const TIPOS: { valor: Tipo; texto: string }[] = [
  { valor: 'escolar', texto: 'Escuela' },
  { valor: 'casa', texto: 'Casa o hábito' },
  { valor: 'actividad', texto: 'Actividad' },
];
const FRECUENCIAS: { valor: Frecuencia; texto: string }[] = [
  { valor: 'una_vez', texto: 'Una vez' },
  { valor: 'diaria', texto: 'Diaria' },
  { valor: 'semanal', texto: 'Algunos días' },
];
const DIAS: { valor: DiaSemana; corto: string; nombre: string }[] = [
  { valor: 'lu', corto: 'L', nombre: 'lunes' },
  { valor: 'ma', corto: 'M', nombre: 'martes' },
  { valor: 'mi', corto: 'M', nombre: 'miércoles' },
  { valor: 'ju', corto: 'J', nombre: 'jueves' },
  { valor: 'vi', corto: 'V', nombre: 'viernes' },
  { valor: 'sa', corto: 'S', nombre: 'sábado' },
  { valor: 'do', corto: 'D', nombre: 'domingo' },
];

const chip = (activo: boolean) => `min-h-tactil rounded-boton px-4 text-etiqueta ${activo ? 'bg-indigo-600 text-blanco' : 'bg-superficie text-tinta-900'}`;

function Grupo({ titulo, error, children }: { titulo: string; error?: string; children: ReactNode }) {
  return (
    <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
      <legend className="mb-2 text-etiqueta">{titulo}</legend>
      {children}
      {error && <span className="text-etiqueta text-indigo-700">{error}</span>}
    </fieldset>
  );
}

interface Props {
  tarea: Tarea | null;
  hoy: string;
  enviando: boolean;
  erroresServidor?: Record<string, string>;
  onGuardar: (datos: TareaPropiaEntrada) => void;
  onCancelar: () => void;
}

/** Tarea propia de una Pro (RF-15): escolar, de casa o actividad, con su recurrencia. */
export function FormTareaPro({ tarea, hoy, enviando, erroresServidor = {}, onGuardar, onCancelar }: Props) {
  const [tipo, setTipo] = useState<Tipo>(tarea?.tipo ?? 'escolar');
  const [b, setB] = useState<BorradorRutina>(() => borradorRutinaDe(tarea, hoy));
  const [tocado, setTocado] = useState(false);
  const errores = validarRutina(b);
  const ver = (k: keyof BorradorRutina) => (tocado ? errores[k] : undefined) ?? erroresServidor[k];
  const cambiar = <K extends keyof BorradorRutina>(k: K, v: BorradorRutina[K]) => setB((x) => ({ ...x, [k]: v }));

  function enviar(e: FormEvent) {
    e.preventDefault();
    setTocado(true);
    if (Object.keys(errores).length === 0) onGuardar(aEntradaRutina(b));
  }

  return (
    <div className="flex flex-col gap-6">
      {/* El tipo no cambia al editar: una escolar sigue siendo escolar. */}
      {!tarea && (
        <Grupo titulo="¿Qué tipo de tarea?">
          <div className="flex flex-wrap gap-2">
            {TIPOS.map((t) => (
              <button
                key={t.valor}
                type="button"
                aria-pressed={tipo === t.valor}
                className={chip(tipo === t.valor)}
                onClick={() => {
                  setTipo(t.valor);
                  if (t.valor !== 'escolar') cambiar('tipo', t.valor);
                }}
              >
                {t.texto}
              </button>
            ))}
          </div>
        </Grupo>
      )}

      {tipo === 'escolar' ? (
        <FormEscolarPropia tarea={tarea} hoy={hoy} puntos={tarea?.puntos} enviando={enviando} erroresServidor={erroresServidor} onGuardar={onGuardar} onCancelar={onCancelar} />
      ) : (
        <form onSubmit={enviar} noValidate className="flex flex-col gap-6">
          <Campo etiqueta="Tarea" placeholder="Ej. Practicar guitarra" maxLength={80} value={b.titulo} onChange={(e) => cambiar('titulo', e.target.value)} error={ver('titulo')} />

          <Grupo titulo="Ícono">
            <div className="grid grid-cols-8 gap-2 sm:grid-cols-12">
              {Object.entries(ICONOS).map(([clave, I]) => (
                <button key={clave} type="button" aria-label={clave} aria-pressed={b.icono === clave} onClick={() => cambiar('icono', clave)} className={`flex aspect-square items-center justify-center rounded-icono ${b.icono === clave ? FONDO_COLOR.indigo : 'bg-superficie text-tinta-900'}`}>
                  <I className="size-1/2" aria-hidden="true" />
                </button>
              ))}
            </div>
          </Grupo>

          <Grupo titulo="¿Cada cuándo?" error={ver('dias_semana')}>
            <div className="flex flex-wrap gap-2">
              {FRECUENCIAS.map((f) => (
                <button key={f.valor} type="button" aria-pressed={b.frecuencia === f.valor} className={chip(b.frecuencia === f.valor)} onClick={() => cambiar('frecuencia', f.valor)}>
                  {f.texto}
                </button>
              ))}
            </div>
            {b.frecuencia === 'semanal' && (
              <div className="flex flex-wrap gap-2">
                {DIAS.map((d) => (
                  <button
                    key={d.valor}
                    type="button"
                    aria-label={d.nombre}
                    aria-pressed={b.dias_semana.includes(d.valor)}
                    className={chip(b.dias_semana.includes(d.valor))}
                    onClick={() => cambiar('dias_semana', b.dias_semana.includes(d.valor) ? b.dias_semana.filter((x) => x !== d.valor) : [...b.dias_semana, d.valor])}
                  >
                    {d.corto}
                  </button>
                ))}
              </div>
            )}
          </Grupo>

          <div className="grid gap-4 sm:grid-cols-3">
            <Campo etiqueta={b.frecuencia === 'una_vez' ? 'Fecha' : 'Desde'} type="date" value={b.fecha_inicio} onChange={(e) => cambiar('fecha_inicio', e.target.value)} error={ver('fecha_inicio')} />
            {b.frecuencia !== 'una_vez' && (
              <Campo etiqueta="Hasta (opcional)" type="date" min={b.fecha_inicio} value={b.fecha_fin} onChange={(e) => cambiar('fecha_fin', e.target.value)} error={ver('fecha_fin')} />
            )}
            <Campo etiqueta="Hora (opcional)" type="time" value={b.hora} onChange={(e) => cambiar('hora', e.target.value)} />
          </div>

          <p className="m-0 rounded-tarjeta-interna bg-lavanda-100 p-4 text-cuerpo text-indigo-700">
            {tarea ? `Vale ${tarea.puntos} puntos. ` : 'Te da puntos. '}Se acreditan cuando papá o mamá aprueben que la hiciste.
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
      )}
    </div>
  );
}
