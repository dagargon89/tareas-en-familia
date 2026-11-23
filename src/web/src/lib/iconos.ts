import {
  Clapperboard, Crown, Droplets, Flame, Heart, House, Medal, Repeat, Star, Tablet, Trees, Waves,
  Apple, Backpack, Bath, Bed, Bike, BookOpen, Brush, Calculator, Carrot, Dog, Drama, Dumbbell,
  FlaskConical, Footprints, Gamepad2, Gift, Globe, HandHeart, IceCream, Languages, Music, Palette,
  PawPrint, Pencil, Pizza, Popcorn, Puzzle, Shirt, ShowerHead, Smile, Sofa, Sparkles, Sprout, Ticket,
  Timer, Trash2, Trophy, Tv, Utensils, WashingMachine,
  type LucideIcon,
} from 'lucide-react';

/**
 * Catálogo único de 52 íconos (doc 08 §5) para tareas, categorías, insignias y recompensas. La clave es lo que se
 * guarda en `tareas.icono`, `categorias.icono`, `insignias.icono` y `recompensas.icono`; nunca
 * se guarda el nombre de Lucide. El backend valida contra la misma lista.
 */
export const ICONOS = {
  cama: Bed,
  libro: BookOpen,
  lapiz: Pencil,
  calculadora: Calculator,
  mochila: Backpack,
  ropa: Shirt,
  comida: Utensils,
  bano: Bath,
  ducha: ShowerHead,
  dientes: Brush,
  basura: Trash2,
  perro: Dog,
  mascota: PawPrint,
  musica: Music,
  arte: Palette,
  bici: Bike,
  ejercicio: Dumbbell,
  trofeo: Trophy,
  regalo: Gift,
  helado: IceCream,
  videojuego: Gamepad2,
  tele: Tv,
  palomitas: Popcorn,
  pizza: Pizza,
  ciencia: FlaskConical,
  mundo: Globe,
  idiomas: Languages,
  sillon: Sofa,
  lavadora: WashingMachine,
  manzana: Apple,
  verdura: Carrot,
  sonrisa: Smile,
  tiempo: Timer,
  rompecabezas: Puzzle,
  teatro: Drama,
  boleto: Ticket,
  ayudar: HandHeart,
  planta: Sprout,
  caminar: Footprints,
  brillo: Sparkles,
  // Categorías, insignias y recompensas del catálogo inicial (CatalogoSeeder).
  casa: House,
  corazon: Heart,
  repetir: Repeat,
  estrella: Star,
  medalla: Medal,
  corona: Crown,
  fuego: Flame,
  cine: Clapperboard,
  tableta: Tablet,
  arboles: Trees,
  nadar: Waves,
  agua: Droplets,
} satisfies Record<string, LucideIcon>;

export type ClaveIcono = keyof typeof ICONOS;

export function iconoDe(clave: string): LucideIcon {
  return (ICONOS as Record<string, LucideIcon>)[clave] ?? Sparkles;
}
