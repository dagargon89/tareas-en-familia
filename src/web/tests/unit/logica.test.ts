import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('canvas-confetti', () => ({ default: vi.fn() }));

import confetti from 'canvas-confetti';
import { celebrar } from '../../src/features/mi-dia/Celebracion.ts';
import { fechaLocal } from '../../src/lib/fechas.ts';

describe('fechaLocal (RN-06)', () => {
  it('a las 23:30 de Ciudad Juárez sigue siendo el mismo día aunque en UTC ya sea el siguiente', () => {
    // 2026-11-23 23:30 en Ciudad Juárez (UTC-7) = 2026-11-24 06:30 UTC
    expect(fechaLocal(new Date('2026-11-24T06:30:00Z'))).toBe('2026-11-23');
  });
});

describe('celebrar (CP-032, parte de frontend)', () => {
  const original = window.matchMedia;
  afterEach(() => {
    window.matchMedia = original;
    vi.mocked(confetti).mockClear();
  });

  it('lanza confeti en Exploradoras', async () => {
    expect(celebrar('exploradoras')).toBe(true);
    await vi.waitFor(() => expect(confetti).toHaveBeenCalledTimes(1));
  });

  it('no lanza confeti con prefers-reduced-motion', () => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: true }) as unknown as typeof window.matchMedia;
    expect(celebrar('exploradoras')).toBe(false);
    expect(confetti).not.toHaveBeenCalled();
  });

  it('Aventureras y Pro celebran sin confeti', () => {
    expect(celebrar('aventureras')).toBe(false);
    expect(celebrar('pro')).toBe(false);
    expect(confetti).not.toHaveBeenCalled();
  });
});
