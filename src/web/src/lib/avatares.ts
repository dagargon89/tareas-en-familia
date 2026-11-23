import {
  Bird, Bug, Cat, Cloud, Dog, Fish, Flower, Heart, Leaf, Moon, Rabbit, Snail, Squirrel, Star, Sun,
  Turtle,
  type LucideIcon,
} from 'lucide-react';
import type { ColorToken } from '../api/ApiClient.ts';

/**
 * 16 avatares (doc 08 §5). Provisionalmente son íconos de Lucide sobre una forma de color;
 * las ilustraciones SVG propias los reemplazan sin cambiar las claves guardadas en `perfiles.avatar`.
 */
export const AVATARES = {
  gato: Cat,
  perro: Dog,
  conejo: Rabbit,
  ardilla: Squirrel,
  tortuga: Turtle,
  pajaro: Bird,
  pez: Fish,
  caracol: Snail,
  catarina: Bug,
  flor: Flower,
  estrella: Star,
  luna: Moon,
  sol: Sun,
  hoja: Leaf,
  corazon: Heart,
  nube: Cloud,
} satisfies Record<string, LucideIcon>;

export function avatarDe(clave: string): LucideIcon {
  return (AVATARES as Record<string, LucideIcon>)[clave] ?? Star;
}

/** Fondo y texto por color de perfil o categoría, siempre de la paleta del doc 08. */
export const FONDO_COLOR: Record<ColorToken, string> = {
  indigo: 'bg-indigo-600 text-blanco',
  ambar: 'bg-ambar-200 text-tinta-900',
  menta: 'bg-menta-200 text-tinta-900',
  rosa: 'bg-rosa-200 text-tinta-900',
  lavanda: 'bg-lavanda-100 text-indigo-700',
};
