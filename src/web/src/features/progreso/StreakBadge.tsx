import { Flame } from 'lucide-react';
import type { NivelUx } from '../../api/ApiClient.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { Chip } from '../../ui/Chip.tsx';

/** Píldora rosa de racha; en cero invita a empezar, nunca regaña (RF-10, RN-01). */
export function StreakBadge({ semanas, nivel }: { semanas: number; nivel: NivelUx }) {
  const v = VOCABULARIO[nivel];
  return (
    <Chip tono="rosa">
      <Flame className="size-[1.1em]" aria-hidden="true" />
      {semanas > 0 ? v.racha(semanas) : v.rachaCero}
    </Chip>
  );
}
