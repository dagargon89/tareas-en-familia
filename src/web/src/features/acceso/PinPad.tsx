import { Delete } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
  titulo: string;
  onCompleto: (pin: string) => void;
  /** Mensaje amable tras un PIN incorrecto. */
  error?: string | null;
  /** Se incrementa con cada PIN incorrecto: limpia los puntos y sacude. */
  fallos?: number;
  bloqueado?: string | null;
  ocupado?: boolean;
}

const TECLAS = ['1', '2', '3', '4', '5', '6', '7', '8', '9'] as const;

/** Teclado de 4 dígitos con puntos que se llenan (RF-01, doc 08 §4). Sin rojo de error. */
export function PinPad({ titulo, onCompleto, error = null, fallos = 0, bloqueado = null, ocupado = false }: Props) {
  const [pin, setPin] = useState('');
  const [sacudir, setSacudir] = useState(false);
  const deshabilitado = ocupado || bloqueado !== null;

  useEffect(() => {
    if (fallos === 0) return;
    setPin('');
    setSacudir(true);
    const t = window.setTimeout(() => setSacudir(false), 320);
    return () => window.clearTimeout(t);
  }, [fallos]);

  useEffect(() => {
    if (deshabilitado) return;
    function alTecla(e: KeyboardEvent) {
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      const tag = (e.target as HTMLElement | null)?.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
      if (/^[0-9]$/.test(e.key)) {
        e.preventDefault();
        setPin((actual) => {
          if (actual.length >= 4) return actual;
          const nuevo = actual + e.key;
          if (nuevo.length === 4) window.setTimeout(() => onCompleto(nuevo), 0);
          return nuevo;
        });
      } else if (e.key === 'Backspace') {
        e.preventDefault();
        setPin((actual) => actual.slice(0, -1));
      }
    }
    window.addEventListener('keydown', alTecla);
    return () => window.removeEventListener('keydown', alTecla);
  }, [deshabilitado, onCompleto]);

  function teclear(d: string) {
    if (deshabilitado || pin.length >= 4) return;
    const nuevo = pin + d;
    setPin(nuevo);
    if (nuevo.length === 4) onCompleto(nuevo);
  }

  const claseTecla =
    'flex min-h-tactil items-center justify-center rounded-full bg-superficie text-display transition duration-150 ease-rebote hover:bg-lavanda-100 hover:text-indigo-700 active:scale-90 motion-reduce:active:scale-100 disabled:opacity-50 disabled:active:scale-100';

  return (
    <div className="mx-auto flex w-full max-w-xs flex-col items-center gap-6">
      <p className="text-titulo text-center">{titulo}</p>
      <div className={`flex gap-4 ${sacudir ? 'sacudir' : ''} ${ocupado ? 'animate-pulse motion-reduce:animate-none' : ''}`} aria-label={`${pin.length} de 4 dígitos`} role="status">
        {[0, 1, 2, 3].map((i) => (
          <span
            key={i}
            className={`size-5 rounded-full border-2 border-indigo-600 transition-colors ${i < pin.length ? 'bg-indigo-600' : 'bg-transparent'}`}
          />
        ))}
      </div>
      <p aria-live="polite" className="min-h-[1.5em] text-cuerpo text-center text-indigo-700">
        {bloqueado ?? error ?? ''}
      </p>
      <div className="grid w-full grid-cols-3 gap-4">
        {TECLAS.map((d) => (
          <button key={d} type="button" className={claseTecla} onClick={() => teclear(d)} disabled={deshabilitado}>
            {d}
          </button>
        ))}
        <span />
        <button type="button" className={claseTecla} onClick={() => teclear('0')} disabled={deshabilitado}>
          0
        </button>
        <button
          type="button"
          className={claseTecla}
          onClick={() => setPin((p) => p.slice(0, -1))}
          disabled={deshabilitado || pin.length === 0}
          aria-label="Borrar"
        >
          <Delete className="size-1/3" aria-hidden="true" />
        </button>
      </div>
    </div>
  );
}
