import { useState, type FormEvent } from 'react';
import type { ColorToken, NivelUx, PerfilAdmin, PerfilEntrada } from '../../api/ApiClient.ts';
import { AVATARES, FONDO_COLOR } from '../../lib/avatares.ts';
import { problemaPin } from '../../lib/pin.ts';
import { NIVELES, NOMBRE_NIVEL } from '../../niveles/nivel.ts';
import { Boton } from '../../ui/Boton.tsx';
import { Campo } from '../../ui/Campo.tsx';

const COLORES: ColorToken[] = ['indigo', 'ambar', 'menta', 'rosa', 'lavanda'];
const GRADOS: Record<NivelUx, string> = {
  exploradoras: '1° a 3° de primaria',
  aventureras: '4° a 6° de primaria',
  pro: 'Secundaria',
};

interface Props {
  perfil: PerfilAdmin | null;
  enviando: boolean;
  erroresServidor?: Record<string, string>;
  onGuardar: (datos: PerfilEntrada) => void;
  onCancelar: () => void;
}

/** Alta y edición de perfil (RF-17). Solo apodo, avatar, color, nivel y PIN (doc 04 §1). */
export function FormPerfil({ perfil, enviando, erroresServidor = {}, onGuardar, onCancelar }: Props) {
  const [apodo, setApodo] = useState(perfil?.apodo ?? '');
  const [avatar, setAvatar] = useState(perfil?.avatar ?? 'gato');
  const [color, setColor] = useState<ColorToken>(perfil?.color ?? 'menta');
  const [nivel, setNivel] = useState<NivelUx>(perfil?.nivel_ux ?? 'aventureras');
  const [pin, setPin] = useState('');
  const [tocado, setTocado] = useState(false);

  const pinRequerido = perfil === null;
  const errorApodo = apodo.trim().length === 0 ? 'Escribe un apodo' : apodo.length > 30 ? 'Máximo 30 caracteres' : null;
  const errorPin = pin === '' ? (pinRequerido ? 'El PIN es obligatorio' : null) : problemaPin(pin);
  const valido = !errorApodo && !errorPin;

  function enviar(e: FormEvent) {
    e.preventDefault();
    setTocado(true);
    if (!valido) return;
    onGuardar({ apodo: apodo.trim(), avatar, color, nivel_ux: nivel, ...(pin ? { pin } : {}) });
  }

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-6">
      <Campo
        etiqueta="Apodo"
        value={apodo}
        maxLength={30}
        onChange={(e) => setApodo(e.target.value)}
        error={(tocado && errorApodo) || erroresServidor.apodo}
        ayuda="Solo un apodo; no se guardan apellidos ni datos personales."
      />

      <fieldset className="flex flex-col gap-2 border-0 p-0">
        <legend className="mb-2 text-etiqueta">Avatar</legend>
        <div className="grid grid-cols-8 gap-2">
          {Object.entries(AVATARES).map(([clave, Icono]) => (
            <button
              key={clave}
              type="button"
              aria-label={clave}
              aria-pressed={avatar === clave}
              onClick={() => setAvatar(clave)}
              className={`flex aspect-square items-center justify-center rounded-full ${FONDO_COLOR[color]} ${avatar === clave ? 'ring-4 ring-indigo-600' : 'opacity-70'}`}
            >
              <Icono className="size-1/2" aria-hidden="true" />
            </button>
          ))}
        </div>
      </fieldset>

      <fieldset className="flex flex-col gap-2 border-0 p-0">
        <legend className="mb-2 text-etiqueta">Color</legend>
        <div className="flex gap-3">
          {COLORES.map((c) => (
            <button
              key={c}
              type="button"
              aria-label={c}
              aria-pressed={color === c}
              onClick={() => setColor(c)}
              className={`size-11 rounded-full ${FONDO_COLOR[c]} ${color === c ? 'ring-4 ring-indigo-600 ring-offset-2' : ''}`}
            />
          ))}
        </div>
      </fieldset>

      <fieldset className="flex flex-col gap-2 border-0 p-0">
        <legend className="mb-2 text-etiqueta">Nivel de experiencia</legend>
        <div className="grid gap-2 sm:grid-cols-3">
          {NIVELES.map((n) => (
            <label key={n} className={`flex min-h-tactil cursor-pointer flex-col justify-center rounded-tarjeta-interna px-4 py-2 ${nivel === n ? 'bg-indigo-600 text-blanco' : 'bg-superficie'}`}>
              <input type="radio" name="nivel" value={n} checked={nivel === n} onChange={() => setNivel(n)} className="sr-only" />
              <span className="text-etiqueta">{NOMBRE_NIVEL[n]}</span>
              <span className={`text-etiqueta ${nivel === n ? 'opacity-80' : 'text-tinta-600'}`}>{GRADOS[n]}</span>
            </label>
          ))}
        </div>
      </fieldset>

      <Campo
        etiqueta={pinRequerido ? 'PIN de 4 dígitos' : 'Nuevo PIN (déjalo vacío para no cambiarlo)'}
        inputMode="numeric"
        autoComplete="off"
        maxLength={4}
        value={pin}
        onChange={(e) => setPin(e.target.value.replace(/\D/g, ''))}
        error={((tocado || pin.length === 4) && errorPin) || erroresServidor.pin}
        ayuda="Sin repetir dígitos ni secuencias como 1234."
      />

      <div className="flex flex-wrap justify-end gap-3">
        <Boton variante="suave" onClick={onCancelar}>
          Cancelar
        </Boton>
        <Boton type="submit" disabled={enviando}>
          {enviando ? 'Guardando…' : perfil ? 'Guardar cambios' : 'Crear perfil'}
        </Boton>
      </div>
    </form>
  );
}
