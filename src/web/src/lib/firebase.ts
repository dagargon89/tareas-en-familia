import { getApps, initializeApp, type FirebaseApp } from 'firebase/app';
import { connectAuthEmulator, getAuth, signInWithCustomToken, signOut, type Auth } from 'firebase/auth';
import {
  addDoc,
  collection,
  connectFirestoreEmulator,
  doc,
  getDoc,
  getFirestore,
  limit,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
  setDoc,
  updateDoc,
  where,
  type Firestore,
  type Timestamp,
  type Unsubscribe,
} from 'firebase/firestore';
import type { ActorChat, ConfigFirebaseCliente, TokenChatRespuesta } from '../api/ApiClient.ts';
import { registrarLimpiadorChat } from './chatSesion.ts';

export const SALA_FAMILIA_ID = 'familia';

export interface UltimoMensaje {
  texto: string;
  autor_id: string;
  autor_apodo: string;
  creado_en: Date | null;
}

export interface SalaChat {
  id: string;
  tipo: 'grupal' | 'individual';
  nombre: string;
  participantes: string[];
  ultimo_mensaje: UltimoMensaje | null;
  lecturas: Record<string, Date | null>;
  actualizado_en: Date | null;
}

export interface MensajeChat {
  id: string;
  autor_id: string;
  autor_tipo: 'padre' | 'hija';
  autor_apodo: string;
  autor_avatar: string;
  autor_color: string;
  texto: string;
  reacciones: Record<string, string[]>;
  eliminado: boolean;
  creado_en: Date | null;
}

interface InstanciaFirebase {
  app: FirebaseApp;
  auth: Auth;
  db: Firestore;
  clave: string;
}

let instancia: InstanciaFirebase | null = null;
let emuladoresConectados = false;
let uidActual: string | null = null;

function aFecha(valor: unknown): Date | null {
  if (valor && typeof valor === 'object' && 'toDate' in valor && typeof (valor as Timestamp).toDate === 'function') {
    return (valor as Timestamp).toDate();
  }
  return null;
}

export function salaIndividualId(actorA: string, actorB: string): string {
  const [primero, segundo] = [actorA, actorB].sort().map((id) => id.replace(':', '-'));
  return `dm_${primero}__${segundo}`;
}

function obtenerInstancia(config: ConfigFirebaseCliente): InstanciaFirebase {
  const clave = `${config.projectId}|${config.emulador ? 'emu' : 'prod'}`;
  if (instancia && instancia.clave === clave) return instancia;

  const existente = getApps().find((a) => a.name === 'tareas-chat');
  const app =
    existente ??
    initializeApp(
      {
        projectId: config.projectId,
        apiKey: config.apiKey || 'demo-tareas-api-key',
      },
      'tareas-chat',
    );

  const auth = getAuth(app);
  const db = getFirestore(app);

  if (config.emulador && !emuladoresConectados) {
    if (config.authEmulatorUrl) {
      connectAuthEmulator(auth, config.authEmulatorUrl, { disableWarnings: true });
    }
    if (config.firestoreEmulatorHost && config.firestoreEmulatorPort) {
      connectFirestoreEmulator(db, config.firestoreEmulatorHost, config.firestoreEmulatorPort);
    }
    emuladoresConectados = true;
  }

  instancia = { app, auth, db, clave };
  registrarLimpiadorChat(async () => {
    uidActual = null;
    if (auth.currentUser) {
      await signOut(auth);
    }
  });
  return instancia;
}

export async function conectarFirebaseChat(sesionChat: TokenChatRespuesta): Promise<Firestore> {
  const fb = obtenerInstancia(sesionChat.configuracion);
  const esperado = sesionChat.actor.actor_id;

  if (fb.auth.currentUser?.uid !== esperado || uidActual !== esperado) {
    if (fb.auth.currentUser) {
      await signOut(fb.auth);
    }
    await signInWithCustomToken(fb.auth, sesionChat.token);
    uidActual = esperado;
  }

  return fb.db;
}

function mapearSala(id: string, data: Record<string, unknown>): SalaChat {
  const ultimo = data.ultimo_mensaje as Record<string, unknown> | null | undefined;
  const lecturasRaw = (data.lecturas as Record<string, unknown> | undefined) ?? {};
  const lecturas: Record<string, Date | null> = {};
  for (const [k, v] of Object.entries(lecturasRaw)) {
    lecturas[k] = aFecha(v);
  }

  return {
    id,
    tipo: data.tipo === 'individual' ? 'individual' : 'grupal',
    nombre: typeof data.nombre === 'string' ? data.nombre : 'Familia',
    participantes: Array.isArray(data.participantes) ? data.participantes.filter((x): x is string => typeof x === 'string') : [],
    ultimo_mensaje: ultimo
      ? {
          texto: typeof ultimo.texto === 'string' ? ultimo.texto : '',
          autor_id: typeof ultimo.autor_id === 'string' ? ultimo.autor_id : '',
          autor_apodo: typeof ultimo.autor_apodo === 'string' ? ultimo.autor_apodo : '',
          creado_en: aFecha(ultimo.creado_en),
        }
      : null,
    lecturas,
    actualizado_en: aFecha(data.actualizado_en),
  };
}

function mapearMensaje(id: string, data: Record<string, unknown>): MensajeChat {
  const reaccionesRaw = (data.reacciones as Record<string, unknown> | undefined) ?? {};
  const reacciones: Record<string, string[]> = {};
  for (const [emoji, lista] of Object.entries(reaccionesRaw)) {
    if (Array.isArray(lista)) {
      const limpia = lista.filter((x): x is string => typeof x === 'string');
      if (limpia.length > 0) reacciones[emoji] = limpia;
    }
  }

  return {
    id,
    autor_id: typeof data.autor_id === 'string' ? data.autor_id : '',
    autor_tipo: data.autor_tipo === 'padre' ? 'padre' : 'hija',
    autor_apodo: typeof data.autor_apodo === 'string' ? data.autor_apodo : '',
    autor_avatar: typeof data.autor_avatar === 'string' ? data.autor_avatar : 'estrella',
    autor_color: typeof data.autor_color === 'string' ? data.autor_color : 'lavanda',
    texto: typeof data.texto === 'string' ? data.texto : '',
    reacciones,
    eliminado: Boolean(data.eliminado),
    creado_en: aFecha(data.creado_en),
  };
}

export function suscribirSalas(
  db: Firestore,
  actor: ActorChat,
  onDatos: (salas: SalaChat[]) => void,
  onError: (err: Error) => void,
): Unsubscribe {
  const col = collection(db, 'salas');
  // Los padres pueden supervisar todas las salas de la familia; las hijas solo donde participan.
  const consulta =
    actor.tipo === 'padre'
      ? query(col, orderBy('actualizado_en', 'desc'))
      : query(col, where('participantes', 'array-contains', actor.actor_id), orderBy('actualizado_en', 'desc'));

  return onSnapshot(
    consulta,
    (snap) => {
      onDatos(snap.docs.map((d) => mapearSala(d.id, d.data() as Record<string, unknown>)));
    },
    (err) => onError(err),
  );
}

export function suscribirMensajes(
  db: Firestore,
  salaId: string,
  onDatos: (mensajes: MensajeChat[]) => void,
  onError: (err: Error) => void,
): Unsubscribe {
  const consulta = query(collection(db, 'salas', salaId, 'mensajes'), orderBy('creado_en', 'asc'), limit(150));

  return onSnapshot(
    consulta,
    (snap) => {
      onDatos(snap.docs.map((d) => mapearMensaje(d.id, d.data() as Record<string, unknown>)));
    },
    (err) => onError(err),
  );
}

export async function asegurarSalaGrupal(db: Firestore, actor: ActorChat, participantes: ActorChat[]): Promise<string> {
  const ref = doc(db, 'salas', SALA_FAMILIA_ID);
  const ids = Array.from(new Set(participantes.map((p) => p.actor_id)));
  const snap = await getDoc(ref);

  if (!snap.exists()) {
    await setDoc(ref, {
      tipo: 'grupal',
      nombre: 'Familia',
      participantes: ids,
      ultimo_mensaje: null,
      lecturas: { [actor.actor_id]: serverTimestamp() },
      actualizado_en: serverTimestamp(),
    });
  } else {
    const actuales = (snap.data().participantes as string[] | undefined) ?? [];
    const faltan = ids.some((id) => !actuales.includes(id)) || actuales.length !== ids.length;
    if (faltan) {
      await updateDoc(ref, {
        participantes: ids,
        actualizado_en: serverTimestamp(),
      });
    }
  }
  return SALA_FAMILIA_ID;
}

export async function asegurarSalaIndividual(db: Firestore, actor: ActorChat, otro: ActorChat): Promise<string> {
  const salaId = salaIndividualId(actor.actor_id, otro.actor_id);
  const ref = doc(db, 'salas', salaId);
  const snap = await getDoc(ref);

  if (!snap.exists()) {
    const participantes = [actor.actor_id, otro.actor_id].sort();
    await setDoc(ref, {
      tipo: 'individual',
      nombre: `${actor.apodo} y ${otro.apodo}`,
      participantes,
      ultimo_mensaje: null,
      lecturas: { [actor.actor_id]: serverTimestamp() },
      actualizado_en: serverTimestamp(),
    });
  }
  return salaId;
}

export async function enviarMensajeFirestore(
  db: Firestore,
  salaId: string,
  actor: ActorChat,
  texto: string,
): Promise<void> {
  const limpio = texto.trim();
  if (!limpio) return;

  await addDoc(collection(db, 'salas', salaId, 'mensajes'), {
    autor_id: actor.actor_id,
    autor_tipo: actor.tipo,
    autor_apodo: actor.apodo,
    autor_avatar: actor.avatar,
    autor_color: actor.color,
    texto: limpio,
    reacciones: {},
    eliminado: false,
    creado_en: serverTimestamp(),
  });

  await updateDoc(doc(db, 'salas', salaId), {
    ultimo_mensaje: {
      texto: limpio.slice(0, 120),
      autor_id: actor.actor_id,
      autor_apodo: actor.apodo,
      creado_en: serverTimestamp(),
    },
    [`lecturas.${actor.actor_id}`]: serverTimestamp(),
    actualizado_en: serverTimestamp(),
  });
}

export async function marcarSalaLeidaFirestore(db: Firestore, salaId: string, actorId: string): Promise<void> {
  const ref = doc(db, 'salas', salaId);
  const snap = await getDoc(ref);
  if (!snap.exists()) return;

  await updateDoc(ref, {
    [`lecturas.${actorId}`]: serverTimestamp(),
  });
}

export async function alternarReaccionFirestore(
  db: Firestore,
  salaId: string,
  mensaje: MensajeChat,
  actorId: string,
  emoji: string,
): Promise<void> {
  const actuales: Record<string, string[]> = {};
  for (const [k, v] of Object.entries(mensaje.reacciones)) {
    actuales[k] = [...v];
  }

  const lista = actuales[emoji] ?? [];
  if (lista.includes(actorId)) {
    const nueva = lista.filter((id) => id !== actorId);
    if (nueva.length === 0) {
      delete actuales[emoji];
    } else {
      actuales[emoji] = nueva;
    }
  } else {
    actuales[emoji] = [...lista, actorId];
  }

  await updateDoc(doc(db, 'salas', salaId, 'mensajes', mensaje.id), {
    reacciones: actuales,
  });
}

export async function eliminarMensajeFirestore(db: Firestore, salaId: string, mensajeId: string): Promise<void> {
  await updateDoc(doc(db, 'salas', salaId, 'mensajes', mensajeId), {
    eliminado: true,
    texto: '',
  });
}

export function tieneNoLeidos(sala: SalaChat, actorId: string): boolean {
  if (!sala.ultimo_mensaje) return false;
  if (sala.ultimo_mensaje.autor_id === actorId) return false;
  const ultimo = sala.ultimo_mensaje.creado_en;
  if (!ultimo) return false;
  const lectura = sala.lecturas[actorId];
  if (!lectura) return true;
  return ultimo.getTime() > lectura.getTime();
}
