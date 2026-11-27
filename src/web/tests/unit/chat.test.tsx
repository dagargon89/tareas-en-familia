import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ActorChat } from '../../src/api/ApiClient.ts';
import { ChatVista } from '../../src/features/chat/ChatPage.tsx';
import { SelectorEmoji } from '../../src/features/chat/SelectorEmoji.tsx';
import type { EstadoChat } from '../../src/hooks/useChat.ts';
import { SALA_FAMILIA_ID } from '../../src/lib/firebase.ts';

afterEach(cleanup);

const PAPA: ActorChat = {
  actor_id: 'padre:1',
  tipo: 'padre',
  id: 1,
  apodo: 'Papá Demo',
  avatar: 'corona',
  color: 'indigo',
  nivel_ux: null,
};

const LUNA: ActorChat = {
  actor_id: 'hija:1',
  tipo: 'hija',
  id: 1,
  apodo: 'Luna',
  avatar: 'conejo',
  color: 'rosa',
  nivel_ux: 'exploradoras',
};

const ESTRELLA: ActorChat = {
  actor_id: 'hija:3',
  tipo: 'hija',
  id: 3,
  apodo: 'Estrella',
  avatar: 'gato',
  color: 'menta',
  nivel_ux: 'pro',
};

function estadoBase(sobre: Partial<EstadoChat> = {}): EstadoChat {
  return {
    cargando: false,
    error: null,
    actor: LUNA,
    participantes: [PAPA, LUNA, ESTRELLA],
    salas: [
      {
        id: SALA_FAMILIA_ID,
        tipo: 'grupal',
        nombre: 'Familia',
        participantes: ['padre:1', 'hija:1', 'hija:3'],
        ultimo_mensaje: { texto: '¡Hola familia!', autor_id: 'padre:1', autor_apodo: 'Papá Demo', creado_en: new Date() },
        lecturas: {},
        actualizado_en: new Date(),
      },
    ],
    salaActivaId: SALA_FAMILIA_ID,
    mensajes: [
      {
        id: 'm1',
        autor_id: 'padre:1',
        autor_tipo: 'padre',
        autor_apodo: 'Papá Demo',
        autor_avatar: 'corona',
        autor_color: 'indigo',
        texto: '¡Hola familia!',
        reacciones: {},
        eliminado: false,
        creado_en: new Date(),
      },
    ],
    cargandoMensajes: false,
    enviando: false,
    esSupervision: false,
    noLeidosPorSala: {},
    abrirGrupal: vi.fn(async () => {}),
    abrirIndividual: vi.fn(async () => {}),
    abrirSalaPorId: vi.fn(),
    enviarMensaje: vi.fn(async () => {}),
    alternarReaccion: vi.fn(async () => {}),
    eliminarMensaje: vi.fn(async () => {}),
    reintentar: vi.fn(),
    ...sobre,
  };
}

describe('Chat familiar', () => {
  it('SelectorEmoji cambia de categoría e inserta el emoji elegido', async () => {
    const user = userEvent.setup();
    const onElegir = vi.fn();
    render(<SelectorEmoji abierto onElegir={onElegir} onCerrar={vi.fn()} />);

    await user.click(screen.getByRole('tab', { name: /Premios/i }));
    await user.click(screen.getByRole('button', { name: 'Insertar 🍦' }));

    expect(onElegir).toHaveBeenCalledWith('🍦');
  });

  it('muestra grupo Familia, chats individuales y frases rápidas para Exploradoras', async () => {
    const user = userEvent.setup();
    const enviarMensaje = vi.fn(async () => {});
    const abrirIndividual = vi.fn(async () => {});
    render(<ChatVista estado={estadoBase({ enviarMensaje, abrirIndividual })} />);

    expect(screen.getByText('Grupo familiar')).toBeInTheDocument();
    expect(screen.getByText('Chats individuales')).toBeInTheDocument();
    expect(screen.queryByText(/Supervisión/i)).not.toBeInTheDocument();

    // Frase rápida de un toque en Exploradoras
    await user.click(screen.getByRole('button', { name: '¡Ya acabé! ⭐' }));
    expect(enviarMensaje).toHaveBeenCalledWith('¡Ya acabé! ⭐');

    // Abrir chat individual con Estrella
    await user.click(screen.getByRole('button', { name: /Estrella/i }));
    expect(abrirIndividual).toHaveBeenCalledWith(ESTRELLA);
  });

  it('en Modo Padre muestra la sección Supervisión para chats entre hermanas y permite moderar', async () => {
    const user = userEvent.setup();
    const eliminarMensaje = vi.fn(async () => {});
    const salaDmHermanas = {
      id: 'dm_hija-1__hija-3',
      tipo: 'individual' as const,
      nombre: 'Luna y Estrella',
      participantes: ['hija:1', 'hija:3'],
      ultimo_mensaje: { texto: 'Hola hermana', autor_id: 'hija:1', autor_apodo: 'Luna', creado_en: new Date() },
      lecturas: {},
      actualizado_en: new Date(),
    };

    render(
      <ChatVista
        estado={estadoBase({
          actor: PAPA,
          salas: [salaDmHermanas],
          salaActivaId: salaDmHermanas.id,
          esSupervision: true,
          mensajes: [
            {
              id: 'm2',
              autor_id: 'hija:1',
              autor_tipo: 'hija',
              autor_apodo: 'Luna',
              autor_avatar: 'conejo',
              autor_color: 'rosa',
              texto: 'Hola hermana',
              reacciones: {},
              eliminado: false,
              creado_en: new Date(),
            },
          ],
          eliminarMensaje,
        })}
      />,
    );

    expect(screen.getByText('Supervisión (hermanas)')).toBeInTheDocument();
    expect(screen.getByText(/Modo supervisión: puedes leer esta conversación/i)).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Eliminar mensaje' }));
    expect(eliminarMensaje).toHaveBeenCalledWith('m2');
  });
});
