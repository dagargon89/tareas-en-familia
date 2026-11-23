import { useState, type FormEvent, type ReactNode } from 'react';
import type { Categoria, DiaSemana, Frecuencia, PerfilPublico, Tarea, TareaEntrada, TipoTarea } from '../../api/ApiClient.ts';
import { FONDO_COLOR } from '../../lib/avatares.ts';
import { ICONOS } from '../../lib/iconos.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';
import { aEntrada, validarTarea, type BorradorTarea } from './validarTarea.ts';

const TIPOS: { valor: TipoTarea; texto: string }[] = [
  { valor: 'casa', texto: 'Casa' },
  { valor: 'escolar', texto: 'Escolar' },
  { valor: 'actividad', texto: 'Actividad' },
];
const FRECUENCIAS: { valor: Frecuencia; texto: string }[] = [
  { valor: 'una_vez', texto: 'Una vez' },
  { valor: 'diaria', texto: 'Diaria' },
  { valor: 'semanal', texto: 'Algunos días' },
];
const DIAS: { valor: DiaSemana; texto: string }[] = [
  { valor: 'lu', texto: 'L' },
  { valor: 'ma', texto: 'M' },
  { valor: 'mi', texto: 'M' },
  { valor: 'ju', texto: 'J' },
  { valor: 'vi', texto: 'V' },
  { valor: 'sa', texto: 'S' },
  { valor: 'do', texto: 'D' },
];
const NOMBRE_DIA: Record<DiaSemana, string> = { lu: 'lunes', ma: 'martes', mi: 'miércoles', ju: 'jueves', vi: 'viernes', sa: 'sábado', do: 'domingo' };

function borradorDe(t: Tarea | null, hoy: string, perfilInicial: number | null): BorradorTarea {
  return {
    perfil_id: t?.perfil_id ?? perfilInicial,
    categoria_id: t?.categoria_id ?? null,
    titulo: t?.titulo ?? '',
    icono: t?.icono ?? 'brillo',
    puntos: t?.puntos ?? 5,
    tipo: t?.tipo ?? 'casa',
    materia: t?.materia ?? '',
    fecha_entrega: t?.fecha_entrega ?? '',
    requiere_foto: t?.requiere_foto ?? false,
    frecuencia: t?.recurrencia.frecuencia ?? 'diaria',
    dias_semana: t?.recurrencia.dias_semana ?? [],
    hora: t?.recurrencia.hora ?? '',
    fecha_inicio: t?.recurrencia.fecha_inicio ?? hoy,
    fecha_fin: t?.recurrencia.fecha_fin ?? '',
  };
}

function Grupo({ titulo, error, children }: { titulo: string; error?: string; children: ReactNode }) {
  return (
    <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
      <legend className="mb-2 text-etiqueta">{titulo}</legend>
      {children}
      {error && <span className="text-etiqueta text-indigo-700">{error}</span>}
    </fieldset>
  );
}

function Opcion({ activa, onClick, children, etiqueta }: { activa: boolean; onClick: () => void; children: ReactNode; etiqueta?: string }) {
  return (
    <button
      type="button"
      aria-pressed={activa}
      aria-label={etiqueta}
      onClick={onClick}
      className={`min-h-tactil rounded-boton px-4 text-etiqueta ${activa ? 'bg-indigo-600 text-blanco' : 'bg-superficie text-tinta-900'}`}
    >
      {children}
    </button>
  );
}

interface Props {
  tarea: Tarea | null;
  perfiles: PerfilPublico[];
  categorias: Categoria[];
  hoy: string;
  perfilInicial?: number | null;
  enviando: boolean;
  erroresServidor?: Record<string, string>;
  onGuardar: (datos: TareaEntrada) => void;
  onCancelar: () => void;
}

/** Formulario de tarea del padre (RF-03, RF-13). Editar solo afecta instancias futuras pendientes. */
export function FormTarea({ tarea, perfiles, categorias, hoy, perfilInicial = null, enviando, erroresServidor = {}, onGuardar, onCancelar }: Props) {
  const [b, setB] = useState<BorradorTarea>(() => borradorDe(tarea, hoy, perfilInicial));
  const [tocado, setTocado] = useState(false);
  const errores = validarTarea(b, hoy);
  const ver = (k: keyof BorradorTarea) => (tocado ? errores[k] : undefined) ?? erroresServidor[k];
  const cambiar = <K extends keyof BorradorTarea>(k: K, v: BorradorTarea[K]) => setB((x) => ({ ...x, [k]: v }));

  function enviar(e: FormEvent) {
    e.preventDefault();
    setTocado(true);
    if (Object.keys(errores).length > 0) return;
    onGuardar(aEntrada(b, hoy));
  }

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-6">
      <Campo etiqueta="Título" maxLength={80} value={b.titulo} onChange={(e) => cambiar('titulo', e.target.value)} error={ver('titulo')} />

      <Grupo titulo="¿Para quién?" error={ver('perfil_id')}>
        <div className="flex flex-wrap gap-2">
          {perfiles.map((p) => (
            <Opcion key={p.id} activa={b.perfil_id === p.id} onClick={() => cambiar('perfil_id', p.id)}>
              {p.apodo}
            </Opcion>
          ))}
        </div>
      </Grupo>

      <Grupo titulo="Tipo">
        <div className="flex flex-wrap gap-2">
          {TIPOS.map((t) => (
            <Opcion key={t.valor} activa={b.tipo === t.valor} onClick={() => cambiar('tipo', t.valor)}>
              {t.texto}
            </Opcion>
          ))}
        </div>
      </Grupo>

      <Grupo titulo="Categoría" error={ver('categoria_id')}>
        <div className="flex flex-wrap gap-2">
          {categorias.map((c) => (
            <Opcion key={c.id} activa={b.categoria_id === c.id} onClick={() => cambiar('categoria_id', c.id)}>
              {c.nombre}
            </Opcion>
          ))}
        </div>
      </Grupo>

      <Grupo titulo="Ícono">
        <div className="grid grid-cols-8 gap-2 sm:grid-cols-10">
          {Object.entries(ICONOS).map(([clave, Icono]) => (
            <button
              key={clave}
              type="button"
              aria-label={clave}
              aria-pressed={b.icono === clave}
              onClick={() => cambiar('icono', clave)}
              className={`flex aspect-square items-center justify-center rounded-icono ${b.icono === clave ? FONDO_COLOR.indigo : 'bg-superficie text-tinta-900'}`}
            >
              <Icono className="size-1/2" aria-hidden="true" />
            </button>
          ))}
        </div>
      </Grupo>

      <Campo
        etiqueta="Puntos"
        type="number"
        inputMode="numeric"
        min={b.tipo === 'actividad' ? 0 : 1}
        max={100}
        value={Number.isNaN(b.puntos) ? '' : b.puntos}
        onChange={(e) => cambiar('puntos', e.target.valueAsNumber)}
        error={ver('puntos')}
        ayuda={b.tipo === 'actividad' ? 'Con 0 puntos no necesita tu aprobación.' : undefined}
      />

      {b.tipo === 'escolar' ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <Campo etiqueta="Materia" maxLength={40} value={b.materia} onChange={(e) => cambiar('materia', e.target.value)} error={ver('materia')} />
          <Campo etiqueta="Fecha de entrega" type="date" min={hoy} value={b.fecha_entrega} onChange={(e) => cambiar('fecha_entrega', e.target.value)} error={ver('fecha_entrega')} ayuda="Aparece en su día desde hoy hasta la entrega." />
        </div>
      ) : (
        <Grupo titulo="¿Cada cuándo?" error={ver('dias_semana')}>
          <div className="flex flex-wrap gap-2">
            {FRECUENCIAS.map((f) => (
              <Opcion key={f.valor} activa={b.frecuencia === f.valor} onClick={() => cambiar('frecuencia', f.valor)}>
                {f.texto}
              </Opcion>
            ))}
          </div>
          {b.frecuencia === 'semanal' && (
            <div className="flex flex-wrap gap-2">
              {DIAS.map((d) => (
                <Opcion
                  key={d.valor}
                  etiqueta={NOMBRE_DIA[d.valor]}
                  activa={b.dias_semana.includes(d.valor)}
                  onClick={() => cambiar('dias_semana', b.dias_semana.includes(d.valor) ? b.dias_semana.filter((x) => x !== d.valor) : [...b.dias_semana, d.valor])}
                >
                  {d.texto}
                </Opcion>
              ))}
            </div>
          )}
          <div className="grid gap-4 sm:grid-cols-2">
            <Campo etiqueta={b.frecuencia === 'una_vez' ? 'Fecha' : 'Desde'} type="date" value={b.fecha_inicio} onChange={(e) => cambiar('fecha_inicio', e.target.value)} error={ver('fecha_inicio')} />
            {b.frecuencia !== 'una_vez' && (
              <Campo etiqueta="Hasta (opcional)" type="date" min={b.fecha_inicio} value={b.fecha_fin} onChange={(e) => cambiar('fecha_fin', e.target.value)} error={ver('fecha_fin')} />
            )}
          </div>
        </Grupo>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <Campo etiqueta="Hora (opcional)" type="time" value={b.hora} onChange={(e) => cambiar('hora', e.target.value)} />
        <label className="flex min-h-tactil items-center gap-3 self-end text-cuerpo">
          <input type="checkbox" className="size-6 accent-[var(--tf-indigo-600)]" checked={b.requiere_foto} onChange={(e) => cambiar('requiere_foto', e.target.checked)} />
          Pide foto al terminar
        </label>
      </div>

      {tarea && <p className="m-0 text-etiqueta text-tinta-600">Los cambios aplican a los días que aún no se hacen; lo ya hecho o aprobado no cambia.</p>}

      <div className="flex flex-wrap justify-end gap-3">
        <Boton variante="suave" onClick={onCancelar}>
          Cancelar
        </Boton>
        <Boton type="submit" disabled={enviando}>
          {enviando ? 'Guardando…' : tarea ? 'Guardar cambios' : 'Crear tarea'}
        </Boton>
      </div>
    </form>
  );
}
