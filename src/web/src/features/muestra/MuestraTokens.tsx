import { useEffect, useState } from 'react';
import { NIVELES, NOMBRE_NIVEL, aplicarNivel, type NivelUx } from '../../niveles/nivel.ts';

const COLORES = [
  { token: 'indigo-600', clase: 'bg-indigo-600', texto: 'text-blanco' },
  { token: 'indigo-700', clase: 'bg-indigo-700', texto: 'text-blanco' },
  { token: 'lavanda-100', clase: 'bg-lavanda-100', texto: 'text-indigo-700' },
  { token: 'ambar-200', clase: 'bg-ambar-200', texto: 'text-tinta-900' },
  { token: 'menta-200', clase: 'bg-menta-200', texto: 'text-tinta-900' },
  { token: 'rosa-200', clase: 'bg-rosa-200', texto: 'text-tinta-900' },
  { token: 'superficie', clase: 'bg-superficie', texto: 'text-tinta-600' },
  { token: 'blanco', clase: 'bg-blanco', texto: 'text-tinta-600' },
] as const;

const TIPOGRAFIA = [
  { rol: 'Display', clase: 'text-display', ejemplo: '¡Hola, Luna!' },
  { rol: 'Título de tarjeta', clase: 'text-titulo', ejemplo: 'Tender la cama' },
  { rol: 'Cuerpo', clase: 'text-cuerpo', ejemplo: 'Te faltan 40 para Árbol' },
  { rol: 'Etiqueta', clase: 'text-etiqueta', ejemplo: '+4 XP' },
] as const;

/** Página de muestra de tokens del doc 08 (T-F1-01). */
export function MuestraTokens() {
  const [nivel, setNivel] = useState<NivelUx>('aventureras');
  const [oscuro, setOscuro] = useState(false);

  useEffect(() => {
    aplicarNivel(nivel);
  }, [nivel]);

  useEffect(() => {
    document.documentElement.dataset.tema = oscuro ? 'oscuro' : 'claro';
  }, [oscuro]);

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-8 p-4 sm:p-8">
      <header className="flex flex-col gap-4">
        <h1 className="text-display">Formas vivas · tokens</h1>
        <div role="radiogroup" aria-label="Nivel de experiencia" className="flex flex-wrap gap-3">
          {NIVELES.map((n) => (
            <button
              key={n}
              type="button"
              role="radio"
              aria-checked={nivel === n}
              onClick={() => setNivel(n)}
              className={`min-h-tactil rounded-boton px-6 text-etiqueta transition-colors ${
                nivel === n ? 'bg-indigo-600 text-blanco' : 'bg-lavanda-100 text-indigo-700'
              }`}
            >
              {NOMBRE_NIVEL[n]}
            </button>
          ))}
          <label className="flex min-h-tactil items-center gap-2 text-etiqueta text-tinta-600">
            <input
              type="checkbox"
              checked={oscuro}
              onChange={(e) => setOscuro(e.target.checked)}
              disabled={nivel === 'exploradoras'}
            />
            Modo oscuro {nivel === 'exploradoras' && '(no disponible en Exploradoras)'}
          </label>
        </div>
      </header>

      <section aria-labelledby="t-color" className="flex flex-col gap-3">
        <h2 id="t-color" className="text-titulo">Color</h2>
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {COLORES.map((c) => (
            <li
              key={c.token}
              className={`${c.clase} ${c.texto} flex h-24 items-end rounded-tarjeta-interna p-3 text-etiqueta`}
            >
              {c.token}
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="t-tipo" className="flex flex-col gap-3">
        <h2 id="t-tipo" className="text-titulo">Tipografía · {NOMBRE_NIVEL[nivel]}</h2>
        <dl className="flex flex-col gap-2 rounded-tarjeta bg-superficie p-6">
          {TIPOGRAFIA.map((t) => (
            <div key={t.rol} className="flex flex-col">
              <dt className="text-etiqueta text-tinta-600">{t.rol}</dt>
              <dd className={`${t.clase} m-0`}>{t.ejemplo}</dd>
            </div>
          ))}
        </dl>
      </section>

      <section aria-labelledby="t-comp" className="flex flex-col gap-3">
        <h2 id="t-comp" className="text-titulo">Tarjeta de ejemplo</h2>
        <article className="flex flex-col gap-4 rounded-tarjeta bg-superficie p-6">
          <div className="flex items-center gap-4">
            <span
              aria-hidden="true"
              className="flex size-icono items-center justify-center rounded-icono bg-menta-200"
              style={{ minWidth: 'var(--tf-icono)' }}
            />
            <div className="flex flex-1 flex-col">
              <h3 className="text-titulo m-0">Leer 20 minutos</h3>
              <span className="text-etiqueta text-tinta-600">Español · 17:00</span>
            </div>
            <span className="rounded-boton bg-ambar-200 px-3 py-1 text-etiqueta">+4 XP</span>
          </div>
          <button
            type="button"
            className="min-h-tactil rounded-boton bg-indigo-600 px-8 text-titulo text-blanco transition-transform duration-150 ease-rebote active:scale-95"
          >
            Listo
          </button>
          <p className="m-0 rounded-tarjeta-interna bg-lavanda-100 p-4 text-cuerpo text-indigo-700">
            Casi. Papá dice: falta la última página.
          </p>
        </article>
      </section>
    </main>
  );
}
