import { useMutation } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import type { MensajeAsistenteHistorial, NivelUx } from '../api/ApiClient.ts';
import { api } from '../api/HttpApiClient.ts';
import { detalleError } from '../lib/errores.ts';

export interface MensajeAsistenteUi {
  id: string;
  rol: 'usuario' | 'asistente';
  texto: string;
}

function sugerenciasIniciales(nivel: NivelUx | null): string[] {
  if (nivel === null) {
    return [
      '¿Qué tengo pendiente por aprobar?',
      '¿Cómo cambio o desbloqueo el PIN de una hija?',
      '¿Cómo va la meta familiar?',
      '¿Cómo agrego al otro papá o mamá?',
    ];
  }
  if (nivel === 'exploradoras') {
    return [
      '¿Qué misiones me faltan hoy?',
      '¿Para qué premio me alcanzan mis estrellas?',
      '¿Cómo gano una insignia?',
      '¿Cómo va la meta de la familia?',
    ];
  }
  if (nivel === 'pro') {
    return [
      '¿Qué tareas tengo pendientes hoy?',
      '¿Cuántos puntos me faltan para un premio?',
      '¿Cómo funciona la racha semanal?',
      '¿Cómo creo una tarea propia?',
    ];
  }
  return [
    '¿Qué misiones me faltan hoy?',
    '¿Para qué premio me alcanza?',
    '¿Cuánto me falta para subir de nivel?',
    '¿Cómo va la meta familiar?',
  ];
}

/**
 * Conversación efímera con el asistente IA (ADR-012).
 * Se limpia automáticamente cuando cambia el actor activo (`claveActor`).
 */
export function useAsistente(claveActor: string, nivel: NivelUx | null) {
  const [mensajes, setMensajes] = useState<MensajeAsistenteUi[]>([]);
  const [sugerencias, setSugerencias] = useState<string[]>(() => sugerenciasIniciales(nivel));
  const [modoDemo, setModoDemo] = useState(false);
  const [aviso, setAviso] = useState<string | null>(null);

  useEffect(() => {
    setMensajes([]);
    setSugerencias(sugerenciasIniciales(nivel));
    setModoDemo(false);
    setAviso(null);
  }, [claveActor, nivel]);

  const mutacion = useMutation({
    mutationFn: ({ texto, historial }: { texto: string; historial: MensajeAsistenteHistorial[] }) =>
      api.preguntarAsistente(texto, historial),
    onSuccess: (res) => {
      setAviso(null);
      setModoDemo(res.modo_demo);
      if (res.sugerencias.length > 0) setSugerencias(res.sugerencias);
      setMensajes((prev) => [
        ...prev,
        { id: `bot-${Date.now()}`, rol: 'asistente', texto: res.respuesta },
      ]);
    },
    onError: (err) => {
      setAviso(detalleError(err).mensaje || 'Ahorita no pude responder. Intenta otra vez en un momento.');
    },
  });

  function enviar(textoCrudo: string) {
    const texto = textoCrudo.trim();
    if (!texto || mutacion.isPending) return;

    const historial: MensajeAsistenteHistorial[] = mensajes.slice(-6).map((m) => ({
      rol: m.rol,
      texto: m.texto,
    }));

    setAviso(null);
    setMensajes((prev) => [...prev, { id: `usr-${Date.now()}`, rol: 'usuario', texto }]);
    mutacion.mutate({ texto, historial });
  }

  function reiniciar() {
    setMensajes([]);
    setSugerencias(sugerenciasIniciales(nivel));
    setAviso(null);
  }

  return {
    mensajes,
    sugerencias,
    modoDemo,
    aviso,
    enviando: mutacion.isPending,
    enviar,
    reiniciar,
  };
}
