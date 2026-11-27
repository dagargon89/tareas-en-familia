import { Crown, Eye, MessageCircle, RefreshCw, Users } from 'lucide-react';
import { useState } from 'react';
import type { ActorChat, ColorToken } from '../../api/ApiClient.ts';
import { useChat, type EstadoChat } from '../../hooks/useChat.ts';
import { useSesion } from '../../hooks/useSesion.ts';
import { FONDO_COLOR, avatarDe } from '../../lib/avatares.ts';
import { SALA_FAMILIA_ID, salaIndividualId, type SalaChat } from '../../lib/firebase.ts';
import { Cargando } from '../../ui/Cargando.tsx';
import { Conversacion } from './Conversacion.tsx';

function AvatarLista({ actor }: { actor: ActorChat }) {
  const fondo = FONDO_COLOR[(actor.color as ColorToken) ?? 'lavanda'] ?? FONDO_COLOR.lavanda;
  if (actor.tipo === 'padre') {
    return (
      <span className={`flex size-11 shrink-0 items-center justify-center rounded-full ${fondo}`}>
        <Crown className="size-5" aria-hidden="true" />
      </span>
    );
  }
  const Icono = avatarDe(actor.avatar);
  return (
    <span className={`flex size-11 shrink-0 items-center justify-center rounded-full ${fondo}`}>
      <Icono className="size-5" aria-hidden="true" />
    </span>
  );
}

function nombreDeSupervision(sala: SalaChat, mapa: Map<string, ActorChat>): string {
  const nombres = sala.participantes.map((id) => mapa.get(id)?.apodo ?? id);
  return nombres.join(' y ');
}

interface ChatVistaProps {
  estado: EstadoChat;
}

/**
 * Vista presentacional del Chat Familiar (fácil de probar con datos inyectados).
 */
export function ChatVista({ estado }: ChatVistaProps) {
  const [mostrarHiloMovil, setMostrarHiloMovil] = useState(false);

  if (estado.cargando) {
    return <Cargando texto="Conectando el chat familiar…" />;
  }

  if (!estado.actor) {
    return (
      <div className="rounded-tarjeta bg-lavanda-100 p-6 text-center">
        <p className="text-cuerpo text-indigo-700">
          {estado.error ?? 'No pudimos abrir el chat ahorita.'}
        </p>
        <button
          type="button"
          onClick={estado.reintentar}
          className="mt-4 inline-flex min-h-tactil items-center gap-2 rounded-boton bg-indigo-600 px-5 text-etiqueta text-blanco hover:bg-indigo-hover"
        >
          <RefreshCw className="size-4" aria-hidden="true" />
          Reintentar
        </button>
      </div>
    );
  }

  const actor = estado.actor;
  const mapaActores = new Map(estado.participantes.map((p) => [p.actor_id, p]));
  const otrosIntegrantes = estado.participantes.filter((p) => p.actor_id !== actor.actor_id);

  const salaFamilia = estado.salas.find((s) => s.id === SALA_FAMILIA_ID) ?? null;
  const salasSupervision =
    actor.tipo === 'padre'
      ? estado.salas.filter((s) => s.tipo === 'individual' && !s.participantes.includes(actor.actor_id))
      : [];

  // Determinar cabecera de la sala activa
  const salaActiva = estado.salas.find((s) => s.id === estado.salaActivaId) ?? null;
  const esGrupal = estado.salaActivaId === SALA_FAMILIA_ID;
  let tituloActivo = 'Familia';
  let subtituloActivo = `${estado.participantes.length} integrantes en el grupo`;
  let avatarOtro: ActorChat | null = null;

  if (!esGrupal && estado.salaActivaId) {
    if (estado.esSupervision && salaActiva) {
      tituloActivo = nombreDeSupervision(salaActiva, mapaActores);
      subtituloActivo = 'Chat individual entre hermanas';
    } else {
      const otro = otrosIntegrantes.find((p) => salaIndividualId(actor.actor_id, p.actor_id) === estado.salaActivaId) ?? null;
      if (otro) {
        tituloActivo = otro.apodo;
        subtituloActivo = otro.tipo === 'padre' ? 'Papá / Mamá' : 'Chat individual';
        avatarOtro = otro;
      }
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2.5">
          <span className="flex size-10 items-center justify-center rounded-icono bg-lavanda-100 text-indigo-700">
            <MessageCircle className="size-5" aria-hidden="true" />
          </span>
          <div>
            <h1 className="text-display">Chat familiar</h1>
          </div>
        </div>
      </div>

      {estado.error && (
        <div role="status" className="flex flex-wrap items-center justify-between gap-2 rounded-tarjeta-interna bg-lavanda-100 px-4 py-3 text-etiqueta text-indigo-700">
          <span>{estado.error}</span>
          <button type="button" onClick={estado.reintentar} className="rounded-boton bg-tarjeta px-3 py-1 text-indigo-700 hover:brightness-95">
            Reintentar
          </button>
        </div>
      )}

      <div className="grid gap-4 md:grid-cols-[280px_1fr]">
        {/* Panel izquierdo: lista de salas */}
        <aside className={`${mostrarHiloMovil ? 'hidden md:block' : 'block'} space-y-4`}>
          {/* Sala grupal */}
          <div className="rounded-tarjeta bg-superficie p-3">
            <p className="mb-2 px-2 text-etiqueta text-tinta-600">Grupo familiar</p>
            <button
              type="button"
              onClick={() => {
                setMostrarHiloMovil(true);
                void estado.abrirGrupal();
              }}
              className={`flex min-h-tactil w-full items-center gap-3 rounded-tarjeta-interna px-3 py-2.5 text-left transition-colors ${
                estado.salaActivaId === SALA_FAMILIA_ID ? 'bg-lavanda-100 text-indigo-700' : 'bg-tarjeta text-tinta-900 hover:bg-lavanda-100/60'
              }`}
            >
              <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-blanco">
                <Users className="size-5" aria-hidden="true" />
              </span>
              <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-2">
                  <span className="truncate text-titulo">Familia</span>
                  {estado.noLeidosPorSala[SALA_FAMILIA_ID] && (
                    <span className="size-2.5 shrink-0 rounded-full bg-indigo-600" aria-label="Mensajes sin leer" />
                  )}
                </div>
                <p className="truncate text-etiqueta text-tinta-600">
                  {salaFamilia?.ultimo_mensaje
                    ? `${salaFamilia.ultimo_mensaje.autor_apodo}: ${salaFamilia.ultimo_mensaje.texto}`
                    : 'Todos juntos aquí'}
                </p>
              </div>
            </button>
          </div>

          {/* Chats individuales */}
          <div className="rounded-tarjeta bg-superficie p-3">
            <p className="mb-2 px-2 text-etiqueta text-tinta-600">Chats individuales</p>
            <ul className="space-y-2">
              {otrosIntegrantes.map((persona) => {
                const dmId = salaIndividualId(actor.actor_id, persona.actor_id);
                const salaDm = estado.salas.find((s) => s.id === dmId);
                const activa = estado.salaActivaId === dmId;
                const sinLeer = Boolean(estado.noLeidosPorSala[dmId]);

                return (
                  <li key={persona.actor_id}>
                    <button
                      type="button"
                      onClick={() => {
                        setMostrarHiloMovil(true);
                        void estado.abrirIndividual(persona);
                      }}
                      className={`flex min-h-tactil w-full items-center gap-3 rounded-tarjeta-interna px-3 py-2 text-left transition-colors ${
                        activa ? 'bg-lavanda-100 text-indigo-700' : 'bg-tarjeta text-tinta-900 hover:bg-lavanda-100/60'
                      }`}
                    >
                      <AvatarLista actor={persona} />
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center justify-between gap-2">
                          <span className="truncate text-cuerpo font-semibold">{persona.apodo}</span>
                          {sinLeer && (
                            <span className="size-2.5 shrink-0 rounded-full bg-indigo-600" aria-label="Mensajes sin leer" />
                          )}
                        </div>
                        <p className="truncate text-etiqueta text-tinta-600">
                          {salaDm?.ultimo_mensaje ? salaDm.ultimo_mensaje.texto : 'Toca para saludar'}
                        </p>
                      </div>
                    </button>
                  </li>
                );
              })}
            </ul>
          </div>

          {/* Supervisión parental: chats 1 a 1 entre hermanas */}
          {actor.tipo === 'padre' && (
            <div className="rounded-tarjeta bg-superficie p-3">
              <div className="mb-2 flex items-center gap-1.5 px-2 text-etiqueta text-tinta-600">
                <Eye className="size-4" aria-hidden="true" />
                <span>Supervisión (hermanas)</span>
              </div>
              {salasSupervision.length === 0 ? (
                <p className="rounded-tarjeta-interna bg-tarjeta px-3 py-2.5 text-etiqueta text-tinta-600">
                  Aún no hay chats individuales entre hermanas.
                </p>
              ) : (
                <ul className="space-y-2">
                  {salasSupervision.map((sala) => {
                    const activa = estado.salaActivaId === sala.id;
                    return (
                      <li key={sala.id}>
                        <button
                          type="button"
                          onClick={() => {
                            setMostrarHiloMovil(true);
                            estado.abrirSalaPorId(sala.id);
                          }}
                          className={`flex min-h-tactil w-full items-center gap-3 rounded-tarjeta-interna px-3 py-2 text-left transition-colors ${
                            activa ? 'bg-ambar-200 text-tinta-900' : 'bg-tarjeta text-tinta-900 hover:bg-ambar-200/60'
                          }`}
                        >
                          <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-ambar-200 text-tinta-900">
                            <Eye className="size-4" aria-hidden="true" />
                          </span>
                          <div className="min-w-0 flex-1">
                            <span className="block truncate text-cuerpo font-semibold">
                              {nombreDeSupervision(sala, mapaActores)}
                            </span>
                            <p className="truncate text-etiqueta text-tinta-600">
                              {sala.ultimo_mensaje ? `${sala.ultimo_mensaje.autor_apodo}: ${sala.ultimo_mensaje.texto}` : 'Sin mensajes'}
                            </p>
                          </div>
                        </button>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
          )}
        </aside>

        {/* Panel derecho: conversación activa */}
        <div className={`${mostrarHiloMovil ? 'block' : 'hidden md:block'}`}>
          <Conversacion
            titulo={tituloActivo}
            subtitulo={subtituloActivo}
            esGrupal={esGrupal}
            avatarOtro={avatarOtro}
            actor={actor}
            mensajes={estado.mensajes}
            cargando={estado.cargandoMensajes}
            enviando={estado.enviando}
            esSupervision={estado.esSupervision}
            onEnviar={estado.enviarMensaje}
            onReaccionar={estado.alternarReaccion}
            onEliminar={estado.eliminarMensaje}
            onVolver={() => setMostrarHiloMovil(false)}
          />
        </div>
      </div>
    </div>
  );
}

/** Pantalla conectada del Chat Familiar (usada en `/chat` para hijas y `/padre/chat` para padres). */
export function ChatPage() {
  const { data } = useSesion();
  const clave = data?.modo_padre_hasta
    ? `padre:${data.usuario?.id ?? 0}`
    : `hija:${data?.perfil_activo?.id ?? 0}`;
  const estado = useChat(clave);
  return <ChatVista estado={estado} />;
}
