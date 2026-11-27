import { RotateCcw, Send, Sparkles, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { NivelUx } from '../../api/ApiClient.ts';
import { useAsistente } from '../../hooks/useAsistente.ts';

interface AsistenteDrawerProps {
  abierto: boolean;
  onCerrar: () => void;
  claveActor: string;
  nombreActor: string;
  nivel: NivelUx | null;
}

function saludoPara(nombre: string, nivel: NivelUx | null): string {
  if (nivel === null) {
    return `Hola ${nombre}. Puedo ayudarte con dudas de Tareas en Familia: aprobaciones, perfiles, PINs, premios, metas y configuración.`;
  }
  if (nivel === 'exploradoras') {
    return `¡Hola ${nombre}! ⭐ Pregúntame sobre tus misiones, estrellas, premios o cómo usar la app.`;
  }
  return `Hola ${nombre}. Pregúntame lo que necesites sobre tus tareas, puntos, premios, rachas o cómo usar Tareas en Familia.`;
}

/**
 * Panel deslizante del asistente IA acotado exclusivamente a la app (ADR-012).
 * Renderiza siempre texto plano (nunca `dangerouslySetInnerHTML`) y usa tonos suaves sin rojo.
 */
export function AsistenteDrawer({ abierto, onCerrar, claveActor, nombreActor, nivel }: AsistenteDrawerProps) {
  const { mensajes, sugerencias, modoDemo, aviso, enviando, enviar, reiniciar } = useAsistente(claveActor, nivel);
  const [texto, setTexto] = useState('');
  const listaRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!abierto) return;
    function alTecla(e: KeyboardEvent) {
      if (e.key === 'Escape') onCerrar();
    }
    document.addEventListener('keydown', alTecla);
    inputRef.current?.focus();
    return () => document.removeEventListener('keydown', alTecla);
  }, [abierto, onCerrar]);

  useEffect(() => {
    if (abierto && listaRef.current) {
      listaRef.current.scrollTop = listaRef.current.scrollHeight;
    }
  }, [abierto, mensajes, enviando]);

  if (!abierto) return null;

  function alEnviar(e: React.FormEvent) {
    e.preventDefault();
    if (!texto.trim() || enviando) return;
    enviar(texto);
    setTexto('');
  }

  return (
    <div className="fixed inset-0 z-40 flex items-end justify-end bg-tinta-900/35 sm:items-stretch" onClick={onCerrar}>
      <section
        role="dialog"
        aria-modal="true"
        aria-label="Asistente de Tareas en Familia"
        className="flex h-[85dvh] w-full flex-col rounded-t-tarjeta bg-fondo sm:h-dvh sm:max-w-md sm:rounded-t-none sm:rounded-l-tarjeta"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="flex items-center gap-3 border-b border-superficie px-4 py-3">
          <span className="flex size-10 items-center justify-center rounded-full bg-lavanda-100 text-indigo-700">
            <Sparkles className="size-5" aria-hidden="true" />
          </span>
          <div className="min-w-0 flex-1">
            <h2 className="truncate text-titulo">Asistente de la app</h2>
            <p className="text-etiqueta text-tinta-600">Solo ayuda de tareas, premios y uso</p>
          </div>
          {mensajes.length > 0 && (
            <button
              type="button"
              onClick={reiniciar}
              title="Nueva consulta"
              aria-label="Nueva consulta"
              className="inline-flex size-11 items-center justify-center rounded-full text-tinta-600 hover:bg-superficie"
            >
              <RotateCcw className="size-5" aria-hidden="true" />
            </button>
          )}
          <button
            type="button"
            onClick={onCerrar}
            aria-label="Cerrar asistente"
            className="inline-flex size-11 items-center justify-center rounded-full text-tinta-600 hover:bg-superficie"
          >
            <X className="size-5" aria-hidden="true" />
          </button>
        </header>

        <div ref={listaRef} className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
          <div className="rounded-tarjeta-interna bg-lavanda-100 p-4 text-cuerpo text-tinta-900">
            {saludoPara(nombreActor, nivel)}
          </div>

          {modoDemo && (
            <div className="rounded-tarjeta-interna bg-ambar-200 px-3 py-2 text-etiqueta text-tinta-900">
              Modo demostración local activo (usa tus datos reales; agrega GEMINI_API_KEY para conectar Gemini).
            </div>
          )}

          {mensajes.map((m) => (
            <div key={m.id} className={`flex ${m.rol === 'usuario' ? 'justify-end' : 'justify-start'}`}>
              <div
                className={`max-w-[85%] whitespace-pre-line rounded-tarjeta-interna px-4 py-3 text-cuerpo ${
                  m.rol === 'usuario' ? 'bg-indigo-600 text-blanco' : 'bg-tarjeta text-tinta-900'
                }`}
              >
                {m.texto}
              </div>
            </div>
          ))}

          {enviando && (
            <div className="flex justify-start" role="status" aria-live="polite">
              <div className="rounded-tarjeta-interna bg-tarjeta px-4 py-3 text-etiqueta text-tinta-600">
                Revisando tus datos…
              </div>
            </div>
          )}

          {aviso && (
            <div role="status" className="rounded-tarjeta-interna bg-lavanda-100 px-4 py-3 text-etiqueta text-indigo-700">
              {aviso}
            </div>
          )}

          {sugerencias.length > 0 && (
            <div className="pt-1">
              <p className="mb-2 text-etiqueta text-tinta-600">Preguntas rápidas</p>
              <div className="flex flex-wrap gap-2">
                {sugerencias.map((pregunta) => (
                  <button
                    key={pregunta}
                    type="button"
                    disabled={enviando}
                    onClick={() => enviar(pregunta)}
                    className="min-h-11 rounded-boton bg-superficie px-3.5 py-2 text-left text-etiqueta text-tinta-900 transition-colors hover:bg-lavanda-100 hover:text-indigo-700 disabled:opacity-60"
                  >
                    {pregunta}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>

        <form onSubmit={alEnviar} className="border-t border-superficie bg-fondo px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
          <div className="flex items-center gap-2">
            <input
              ref={inputRef}
              type="text"
              value={texto}
              maxLength={400}
              onChange={(e) => setTexto(e.target.value)}
              placeholder="Pregunta sobre tareas o premios…"
              aria-label="Escribe tu pregunta sobre la app"
              className="min-h-12 flex-1 rounded-boton bg-tarjeta px-4 text-cuerpo text-tinta-900 placeholder:text-tinta-600 focus:outline-2 focus:outline-indigo-600"
            />
            <button
              type="submit"
              disabled={!texto.trim() || enviando}
              aria-label="Enviar pregunta"
              className="inline-flex size-12 shrink-0 items-center justify-center rounded-boton bg-indigo-600 text-blanco transition-colors hover:bg-indigo-hover disabled:opacity-50"
            >
              <Send className="size-5" aria-hidden="true" />
            </button>
          </div>
        </form>
      </section>
    </div>
  );
}
