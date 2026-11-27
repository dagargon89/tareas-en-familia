import { ArrowLeft, Crown, Eye, Send, Smile, Trash2, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ActorChat, ColorToken } from '../../api/ApiClient.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { FRASES_EXPLORADORAS, REACCIONES_RAPIDAS } from '../../lib/emojis.ts';
import type { MensajeChat } from '../../lib/firebase.ts';
import { SelectorEmoji } from './SelectorEmoji.tsx';

interface ConversacionProps {
  titulo: string;
  subtitulo: string;
  esGrupal: boolean;
  avatarOtro?: ActorChat | null;
  actor: ActorChat;
  mensajes: MensajeChat[];
  cargando: boolean;
  enviando: boolean;
  esSupervision: boolean;
  onEnviar: (texto: string) => Promise<void>;
  onReaccionar: (mensaje: MensajeChat, emoji: string) => Promise<void>;
  onEliminar: (mensajeId: string) => Promise<void>;
  onVolver?: () => void;
}

function formatearHora(fecha: Date | null): string {
  if (!fecha) return '';
  return fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
}

function etiquetaDia(fecha: Date | null): string {
  if (!fecha) return 'Hoy';
  const hoy = new Date();
  const mismoDia = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  if (mismoDia(fecha, hoy)) return 'Hoy';
  const ayer = new Date(hoy);
  ayer.setDate(hoy.getDate() - 1);
  if (mismoDia(fecha, ayer)) return 'Ayer';
  return fecha.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' });
}

function AvatarActor({ actor, tamano = 'size-10' }: { actor: Pick<ActorChat, 'tipo' | 'avatar' | 'color'>; tamano?: string }) {
  const fondo = FONDO_COLOR[(actor.color as ColorToken) ?? 'lavanda'] ?? FONDO_COLOR.lavanda;
  if (actor.tipo === 'padre') {
    return (
      <span className={`flex shrink-0 items-center justify-center rounded-full ${fondo} ${tamano}`}>
        <Crown className="size-5" aria-hidden="true" />
      </span>
    );
  }
  const Icono = avatarDe(actor.avatar);
  return (
    <span className={`flex shrink-0 items-center justify-center rounded-full ${fondo} ${tamano}`}>
      <Icono className="size-5" aria-hidden="true" />
    </span>
  );
}

/**
 * Hilo de mensajes del chat familiar con selector local de emojis, reacciones rápidas
 * y frases de un toque para Exploradoras (ADR-011).
 */
export function Conversacion({
  titulo,
  subtitulo,
  esGrupal,
  avatarOtro,
  actor,
  mensajes,
  cargando,
  enviando,
  esSupervision,
  onEnviar,
  onReaccionar,
  onEliminar,
  onVolver,
}: ConversacionProps) {
  const [texto, setTexto] = useState('');
  const [emojisAbiertos, setEmojisAbiertos] = useState(false);
  const [mensajeActivoReaccion, setMensajeActivoReaccion] = useState<string | null>(null);
  const finRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const esExploradora = actor.nivel_ux === 'exploradoras';

  useEffect(() => {
    if (typeof finRef.current?.scrollIntoView === 'function') {
      finRef.current.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
  }, [mensajes.length]);

  async function alEnviar(e: React.FormEvent) {
    e.preventDefault();
    const limpio = texto.trim();
    if (!limpio || enviando || esSupervision) return;
    setTexto('');
    setEmojisAbiertos(false);
    await onEnviar(limpio);
    inputRef.current?.focus();
  }

  function insertarEmoji(emoji: string) {
    setTexto((prev) => (prev + emoji).slice(0, 500));
    inputRef.current?.focus();
  }

  let ultimoDia = '';

  return (
    <section className="flex h-full min-h-[62dvh] flex-col rounded-tarjeta bg-superficie p-3 sm:p-4">
      {/* Cabecera de la conversación */}
      <header className="mb-3 flex items-center gap-3 rounded-tarjeta-interna bg-tarjeta px-3 py-2.5">
        {onVolver && (
          <button
            type="button"
            onClick={onVolver}
            aria-label="Volver a conversaciones"
            className="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-indigo-700 hover:bg-lavanda-100 md:hidden"
          >
            <ArrowLeft className="size-5" aria-hidden="true" />
          </button>
        )}
        {esGrupal ? (
          <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-lavanda-100 text-indigo-700">
            <Users className="size-5" aria-hidden="true" />
          </span>
        ) : avatarOtro ? (
          <AvatarActor actor={avatarOtro} />
        ) : (
          <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-ambar-200 text-tinta-900">
            <Eye className="size-5" aria-hidden="true" />
          </span>
        )}
        <div className="min-w-0 flex-1">
          <h2 className="truncate text-titulo">{titulo}</h2>
          <p className="truncate text-etiqueta text-tinta-600">{subtitulo}</p>
        </div>
        {esSupervision && (
          <span className="inline-flex items-center gap-1 rounded-boton bg-ambar-200 px-3 py-1 text-etiqueta text-tinta-900">
            <Eye className="size-4" aria-hidden="true" />
            Supervisión
          </span>
        )}
      </header>

      {/* Lista de mensajes */}
      <div className="flex-1 space-y-3 overflow-y-auto px-1 py-2">
        {cargando ? (
          <p className="py-8 text-center text-etiqueta text-tinta-600">Cargando mensajes…</p>
        ) : mensajes.length === 0 ? (
          <div className="mx-auto my-8 max-w-sm rounded-tarjeta-interna bg-tarjeta p-5 text-center">
            <p className="text-[32px]" aria-hidden="true">💬</p>
            <p className="mt-2 text-cuerpo text-tinta-900">
              {esSupervision
                ? 'Todavía no hay mensajes en esta conversación.'
                : '¡Empieza la plática! Manda un saludo o un emoji.'}
            </p>
          </div>
        ) : (
          mensajes.map((m) => {
            const esMio = m.autor_id === actor.actor_id;
            const dia = etiquetaDia(m.creado_en);
            const mostrarDia = dia !== ultimoDia;
            ultimoDia = dia;
            const puedeBorrar = !m.eliminado && (esMio || actor.tipo === 'padre');
            const entradasReaccion = Object.entries(m.reacciones).filter(([, lista]) => lista.length > 0);
            const barraAbierta = mensajeActivoReaccion === m.id;

            return (
              <div key={m.id}>
                {mostrarDia && (
                  <div className="my-3 flex justify-center">
                    <span className="rounded-boton bg-tarjeta px-3 py-0.5 text-[12px] font-medium text-tinta-600">
                      {dia}
                    </span>
                  </div>
                )}

                <div className={`flex items-end gap-2 ${esMio ? 'justify-end' : 'justify-start'}`}>
                  {!esMio && (
                    <AvatarActor
                      actor={{ tipo: m.autor_tipo, avatar: m.autor_avatar, color: m.autor_color as ColorToken }}
                      tamano="size-8"
                    />
                  )}

                  <div className={`max-w-[82%] space-y-1 ${esMio ? 'items-end' : 'items-start'} flex flex-col`}>
                    {!esMio && (esGrupal || esSupervision) && (
                      <span className="px-2 text-[12px] font-medium text-tinta-600">{m.autor_apodo}</span>
                    )}

                    <div
                      className={`group relative rounded-tarjeta-interna px-4 py-2.5 text-cuerpo ${
                        m.eliminado
                          ? 'bg-lavanda-100 text-tinta-600 italic'
                          : esMio
                            ? 'bg-indigo-600 text-blanco'
                            : 'bg-tarjeta text-tinta-900'
                      }`}
                    >
                      <p className="whitespace-pre-wrap break-words">
                        {m.eliminado ? 'Mensaje eliminado' : m.texto}
                      </p>
                      <div className={`mt-1 flex items-center justify-end gap-2 text-[11px] ${esMio && !m.eliminado ? 'text-lavanda-100' : 'text-tinta-600'}`}>
                        <span>{formatearHora(m.creado_en)}</span>
                        {!m.eliminado && !esSupervision && (
                          <button
                            type="button"
                            onClick={() => setMensajeActivoReaccion(barraAbierta ? null : m.id)}
                            aria-label="Reaccionar al mensaje"
                            className="rounded-boton px-1.5 py-0.5 hover:bg-superficie/30"
                          >
                            ☺
                          </button>
                        )}
                        {puedeBorrar && (
                          <button
                            type="button"
                            onClick={() => void onEliminar(m.id)}
                            aria-label="Eliminar mensaje"
                            title="Eliminar mensaje"
                            className="rounded-boton p-0.5 hover:bg-superficie/30"
                          >
                            <Trash2 className="size-3.5" aria-hidden="true" />
                          </button>
                        )}
                      </div>
                    </div>

                    {/* Barra desplegable de reacciones rápidas */}
                    {barraAbierta && !m.eliminado && !esSupervision && (
                      <div className="flex items-center gap-1 rounded-boton bg-tarjeta px-2 py-1" role="group" aria-label="Reacciones rápidas">
                        {REACCIONES_RAPIDAS.map((emoji) => (
                          <button
                            key={emoji}
                            type="button"
                            aria-label={`Reaccionar con ${emoji}`}
                            onClick={() => {
                              setMensajeActivoReaccion(null);
                              void onReaccionar(m, emoji);
                            }}
                            className="flex size-9 items-center justify-center rounded-full text-[18px] hover:bg-lavanda-100"
                          >
                            {emoji}
                          </button>
                        ))}
                      </div>
                    )}

                    {/* Chips de reacciones existentes */}
                    {entradasReaccion.length > 0 && !m.eliminado && (
                      <div className="flex flex-wrap gap-1 px-1">
                        {entradasReaccion.map(([emoji, lista]) => {
                          const mia = lista.includes(actor.actor_id);
                          return (
                            <button
                              key={emoji}
                              type="button"
                              disabled={esSupervision}
                              onClick={() => void onReaccionar(m, emoji)}
                              className={`inline-flex items-center gap-1 rounded-boton px-2 py-0.5 text-[13px] transition-colors ${
                                mia ? 'bg-lavanda-100 text-indigo-700' : 'bg-tarjeta text-tinta-900 hover:bg-lavanda-100'
                              }`}
                            >
                              <span>{emoji}</span>
                              <span>{lista.length}</span>
                            </button>
                          );
                        })}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            );
          })
        )}
        <div ref={finRef} />
      </div>

      {/* Pie de entrada o aviso de solo supervisión */}
      {esSupervision ? (
        <div className="mt-2 rounded-tarjeta-interna bg-ambar-200 px-4 py-3 text-etiqueta text-tinta-900">
          Modo supervisión: puedes leer esta conversación entre hermanas y eliminar cualquier mensaje con el ícono de papelera si hace falta.
        </div>
      ) : (
        <div className="mt-2">
          {esExploradora && (
            <div className="mb-2 flex gap-2 overflow-x-auto pb-1" aria-label="Mensajes rápidos">
              {FRASES_EXPLORADORAS.map((frase) => (
                <button
                  key={frase}
                  type="button"
                  disabled={enviando}
                  onClick={() => void onEnviar(frase)}
                  className="min-h-11 shrink-0 rounded-boton bg-tarjeta px-4 text-etiqueta text-indigo-700 transition-colors hover:bg-lavanda-100 disabled:opacity-50"
                >
                  {frase}
                </button>
              ))}
            </div>
          )}

          <SelectorEmoji
            abierto={emojisAbiertos}
            onElegir={insertarEmoji}
            onCerrar={() => setEmojisAbiertos(false)}
          />

          <form onSubmit={alEnviar} className="flex items-center gap-2 rounded-boton bg-tarjeta p-1.5">
            <button
              type="button"
              onClick={() => setEmojisAbiertos((v) => !v)}
              aria-expanded={emojisAbiertos}
              aria-label={emojisAbiertos ? 'Cerrar emojis' : 'Abrir emojis'}
              className={`inline-flex size-11 shrink-0 items-center justify-center rounded-full transition-colors ${
                emojisAbiertos ? 'bg-lavanda-100 text-indigo-700' : 'text-tinta-600 hover:bg-superficie'
              }`}
            >
              <Smile className="size-6" aria-hidden="true" />
            </button>
            <input
              ref={inputRef}
              type="text"
              value={texto}
              maxLength={500}
              onChange={(e) => setTexto(e.target.value)}
              placeholder={esExploradora ? 'Escribe aquí o toca un emoji…' : 'Escribe un mensaje…'}
              aria-label="Escribe un mensaje"
              className="min-h-11 flex-1 bg-transparent px-2 text-cuerpo text-tinta-900 placeholder:text-tinta-600 focus:outline-none"
            />
            <button
              type="submit"
              disabled={!texto.trim() || enviando}
              aria-label="Enviar mensaje"
              className="inline-flex size-11 shrink-0 items-center justify-center rounded-boton bg-indigo-600 text-blanco transition-colors hover:bg-indigo-hover disabled:opacity-50"
            >
              <Send className="size-5" aria-hidden="true" />
            </button>
          </form>
        </div>
      )}
    </section>
  );
}
