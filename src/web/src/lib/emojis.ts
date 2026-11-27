/**
 * Catálogo local de emojis familiares y frases rápidas para el chat (ADR-011).
 * 100 % Unicode nativo: cero recursos externos ni CDNs.
 */

export interface CategoriaEmoji {
  id: string;
  nombre: string;
  icono: string;
  emojis: readonly string[];
}

export const REACCIONES_RAPIDAS = ['❤️', '⭐', '👍', '🎉', '😂', '🙌'] as const;

export const FRASES_EXPLORADORAS = [
  '¡Ya acabé! ⭐',
  'Te quiero ❤️',
  '¿Me ayudas? 🙋‍♀️',
  '¡Yupi! 🎉',
  '¡Ya voy! 🏃‍♀️',
  '¡Gracias! 🌸',
] as const;

export const CATEGORIAS_EMOJI: readonly CategoriaEmoji[] = [
  {
    id: 'caritas',
    nombre: 'Caritas',
    icono: '😊',
    emojis: [
      '😊', '😁', '😄', '🥰', '😍', '🤩', '🥳', '😂', '🤣', '😉',
      '🤗', '😇', '😎', '🤔', '😮', '😴', '🥹', '😅', '🙌', '👏',
    ],
  },
  {
    id: 'carino',
    nombre: 'Familia',
    icono: '❤️',
    emojis: [
      '❤️', '🩷', '💜', '💛', '💚', '💙', '🫶', '🤝', '👍', '👌',
      '✌️', '💪', '🙏', '💐', '🌸', '🌻', '🌈', '☀️', '🌙', '✨',
    ],
  },
  {
    id: 'celebracion',
    nombre: 'Logros',
    icono: '🎉',
    emojis: [
      '⭐', '🌟', '🎉', '🎊', '🏆', '🥇', '🏅', '🎖️', '👑', '🔥',
      '💯', '🎯', '🚀', '🎈', '🎁', '🪄', '💫', '✅', '🥳', '🎶',
    ],
  },
  {
    id: 'tareas',
    nombre: 'Tareas',
    icono: '📚',
    emojis: [
      '📚', '✏️', '📝', '🧮', '🔬', '🎨', '🎵', '🎹', '🏊‍♀️', '🚲',
      '🛏️', '🪥', '🧹', '🍽️', '🪴', '🐶', '🐱', '🐰', '🎒', '⏰',
    ],
  },
  {
    id: 'premios',
    nombre: 'Premios',
    icono: '🍦',
    emojis: [
      '🍦', '🍨', '🍕', '🍿', '🎬', '🎮', '🎲', '🧩', '🌳', '🧁',
      '🍓', '🍉', '🍎', '🍪', '🍫', '🧃', '🎡', '🏖️', '🧸', '📖',
    ],
  },
];
