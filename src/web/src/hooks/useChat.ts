import { useQuery } from '@tanstack/react-query';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ActorChat, TokenChatRespuesta } from '../api/ApiClient.ts';
import { api } from '../api/HttpApiClient.ts';
import {
  SALA_FAMILIA_ID,
  alternarReaccionFirestore,
  asegurarSalaGrupal,
  asegurarSalaIndividual,
  conectarFirebaseChat,
  eliminarMensajeFirestore,
  enviarMensajeFirestore,
  marcarSalaLeidaFirestore,
  salaIndividualId,
  suscribirMensajes,
  suscribirSalas,
  tieneNoLeidos,
  type MensajeChat,
  type SalaChat,
} from '../lib/firebase.ts';
import type { Firestore } from 'firebase/firestore';

export interface EstadoChat {
  cargando: boolean;
  error: string | null;
  actor: ActorChat | null;
  participantes: ActorChat[];
  salas: SalaChat[];
  salaActivaId: string | null;
  mensajes: MensajeChat[];
  cargandoMensajes: boolean;
  enviando: boolean;
  esSupervision: boolean;
  noLeidosPorSala: Record<string, boolean>;
  abrirGrupal: () => Promise<void>;
  abrirIndividual: (otro: ActorChat) => Promise<void>;
  abrirSalaPorId: (salaId: string) => void;
  enviarMensaje: (texto: string) => Promise<void>;
  alternarReaccion: (mensaje: MensajeChat, emoji: string) => Promise<void>;
  eliminarMensaje: (mensajeId: string) => Promise<void>;
  reintentar: () => void;
}

/**
 * Orquesta el chat familiar en tiempo real con Firebase Auth (Custom Token) + Firestore (ADR-011).
 */
export function useChat(claveSesion: string): EstadoChat {
  const tokenQuery = useQuery<TokenChatRespuesta>({
    queryKey: ['chat', 'token', claveSesion],
    queryFn: () => api.tokenChat(),
    staleTime: 45 * 60_000,
    retry: 1,
  });

  const dbRef = useRef<Firestore | null>(null);
  const [conectado, setConectado] = useState(false);
  const [errorFirebase, setErrorFirebase] = useState<string | null>(null);
  const [salas, setSalas] = useState<SalaChat[]>([]);
  const [salaActivaId, setSalaActivaId] = useState<string | null>(SALA_FAMILIA_ID);
  const [mensajes, setMensajes] = useState<MensajeChat[]>([]);
  const [cargandoMensajes, setCargandoMensajes] = useState(false);
  const [enviando, setEnviando] = useState(false);

  const actor = tokenQuery.data?.actor ?? null;
  const participantes = useMemo(() => tokenQuery.data?.participantes ?? [], [tokenQuery.data?.participantes]);

  // 1. Conectar Firebase Auth y suscribirse a salas
  useEffect(() => {
    const sesionChat = tokenQuery.data;
    if (!sesionChat) return;

    let cancelado = false;
    let unsubSalas: (() => void) | null = null;

    async function conectar() {
      if (!sesionChat) return;
      try {
        setErrorFirebase(null);
        const db = await conectarFirebaseChat(sesionChat);
        if (cancelado) return;
        dbRef.current = db;
        setConectado(true);

        await asegurarSalaGrupal(db, sesionChat.actor, sesionChat.participantes);
        if (cancelado) return;

        unsubSalas = suscribirSalas(
          db,
          sesionChat.actor,
          (lista) => {
            if (!cancelado) setSalas(lista);
          },
          () => {
            if (!cancelado) {
              setErrorFirebase('No pudimos conectarnos al chat ahorita. Revisa tu conexión e intenta otra vez.');
            }
          },
        );
      } catch {
        if (!cancelado) {
          setConectado(false);
          setErrorFirebase('No pudimos conectar el chat familiar. Verifica que el emulador o Firebase esté activo.');
        }
      }
    }

    void conectar();

    return () => {
      cancelado = true;
      unsubSalas?.();
    };
  }, [tokenQuery.data]);

  // 2. Suscribirse a mensajes de la sala activa
  useEffect(() => {
    const db = dbRef.current;
    if (!conectado || !db || !salaActivaId || !actor) {
      setMensajes([]);
      return;
    }

    setCargandoMensajes(true);
    const unsub = suscribirMensajes(
      db,
      salaActivaId,
      (lista) => {
        setMensajes(lista);
        setCargandoMensajes(false);
        void marcarSalaLeidaFirestore(db, salaActivaId, actor.actor_id).catch(() => {});
      },
      () => {
        setCargandoMensajes(false);
        setErrorFirebase('No pudimos cargar los mensajes de esta conversación.');
      },
    );

    return () => unsub();
  }, [conectado, salaActivaId, actor]);

  const abrirGrupal = useCallback(async () => {
    const db = dbRef.current;
    if (!db || !actor) {
      setSalaActivaId(SALA_FAMILIA_ID);
      return;
    }
    try {
      await asegurarSalaGrupal(db, actor, participantes);
      setSalaActivaId(SALA_FAMILIA_ID);
    } catch {
      setErrorFirebase('No se pudo abrir el grupo familiar.');
    }
  }, [actor, participantes]);

  const abrirIndividual = useCallback(
    async (otro: ActorChat) => {
      if (!actor) return;
      const idEsperado = salaIndividualId(actor.actor_id, otro.actor_id);
      const db = dbRef.current;
      if (!db) {
        setSalaActivaId(idEsperado);
        return;
      }
      try {
        const id = await asegurarSalaIndividual(db, actor, otro);
        setSalaActivaId(id);
      } catch {
        setErrorFirebase('No se pudo abrir esa conversación.');
      }
    },
    [actor],
  );

  const abrirSalaPorId = useCallback((salaId: string) => {
    setSalaActivaId(salaId);
  }, []);

  const enviarMensaje = useCallback(
    async (texto: string) => {
      const db = dbRef.current;
      if (!db || !actor || !salaActivaId) return;
      setEnviando(true);
      setErrorFirebase(null);
      try {
        await enviarMensajeFirestore(db, salaActivaId, actor, texto);
      } catch {
        setErrorFirebase('No se pudo enviar el mensaje ahorita. Intenta otra vez.');
      } finally {
        setEnviando(false);
      }
    },
    [actor, salaActivaId],
  );

  const alternarReaccion = useCallback(
    async (mensaje: MensajeChat, emoji: string) => {
      const db = dbRef.current;
      if (!db || !actor || !salaActivaId) return;
      try {
        await alternarReaccionFirestore(db, salaActivaId, mensaje, actor.actor_id, emoji);
      } catch {
        // Silencioso en caso de conflicto de red momentáneo.
      }
    },
    [actor, salaActivaId],
  );

  const eliminarMensaje = useCallback(
    async (mensajeId: string) => {
      const db = dbRef.current;
      if (!db || !salaActivaId) return;
      try {
        await eliminarMensajeFirestore(db, salaActivaId, mensajeId);
      } catch {
        setErrorFirebase('No se pudo borrar el mensaje.');
      }
    },
    [salaActivaId],
  );

  const salaActiva = salas.find((s) => s.id === salaActivaId) ?? null;
  const esSupervision = Boolean(
    actor?.tipo === 'padre' && salaActiva && !salaActiva.participantes.includes(actor.actor_id),
  );

  const noLeidosPorSala: Record<string, boolean> = {};
  if (actor) {
    for (const s of salas) {
      if (s.participantes.includes(actor.actor_id)) {
        noLeidosPorSala[s.id] = tieneNoLeidos(s, actor.actor_id);
      }
    }
  }

  return {
    cargando: tokenQuery.isLoading || (tokenQuery.isSuccess && !conectado && !errorFirebase),
    error: errorFirebase ?? (tokenQuery.isError ? 'No pudimos iniciar el chat familiar.' : null),
    actor,
    participantes,
    salas,
    salaActivaId,
    mensajes,
    cargandoMensajes,
    enviando,
    esSupervision,
    noLeidosPorSala,
    abrirGrupal,
    abrirIndividual,
    abrirSalaPorId,
    enviarMensaje,
    alternarReaccion,
    eliminarMensaje,
    reintentar: () => {
      setErrorFirebase(null);
      void tokenQuery.refetch();
    },
  };
}
