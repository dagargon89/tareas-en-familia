import { useState, type FormEvent } from 'react';
import type { InstanciaRevision } from '../../api/ApiClient.ts';
import { Boton } from '../../ui/Boton.tsx';

const SUGERENCIAS = ['Te faltó un poquito', 'Vamos a revisarla juntos', 'Falta la foto', 'Casi, inténtalo otra vez'];
export const MAX_MENSAJE = 140;

interface Props {
  item: InstanciaRevision;
  enviando: boolean;
  onDevolver: (mensaje: string) => void;
  onCancelar: () => void;
}

/** Devolución con mensaje amable obligatorio de hasta 140 caracteres (RF-07, CP-047). */
export function DevolverForm({ item, enviando, onDevolver, onCancelar }: Props) {
  const [mensaje, setMensaje] = useState('');
  const limpio = mensaje.trim();
  const valido = limpio.length > 0 && limpio.length <= MAX_MENSAJE;

  function enviar(e: FormEvent) {
    e.preventDefault();
    if (valido) onDevolver(limpio);
  }

  return (
    <form onSubmit={enviar} className="flex flex-col gap-3 rounded-tarjeta bg-lavanda-100 p-4 text-indigo-700">
      <label htmlFor={`devolver-${item.id}`} className="text-etiqueta">
        ¿Qué le dices a {item.perfil.apodo}? Lo verá en su tarjeta.
      </label>
      <div className="flex flex-wrap gap-2">
        {SUGERENCIAS.map((s) => (
          <button key={s} type="button" onClick={() => setMensaje(s)} className="min-h-11 rounded-boton bg-tarjeta px-3 text-etiqueta">
            {s}
          </button>
        ))}
      </div>
      <textarea
        id={`devolver-${item.id}`}
        rows={2}
        maxLength={MAX_MENSAJE}
        value={mensaje}
        onChange={(e) => setMensaje(e.target.value)}
        className="rounded-tarjeta-interna border-2 border-transparent bg-tarjeta p-3 text-cuerpo text-tinta-900 outline-none focus:border-indigo-600"
      />
      <span className="self-end text-etiqueta">
        {limpio.length}/{MAX_MENSAJE}
      </span>
      <div className="flex justify-end gap-2">
        <Boton variante="suave" onClick={onCancelar}>
          Cancelar
        </Boton>
        <Boton type="submit" disabled={!valido || enviando}>
          {enviando ? 'Enviando…' : 'Devolver'}
        </Boton>
      </div>
    </form>
  );
}
