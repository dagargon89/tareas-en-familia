import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { AVATARES } from '../../src/lib/avatares.ts';
import { ICONOS } from '../../src/lib/iconos.ts';

// Catálogo único (doc 08 §5): toda clave que siembra el backend debe existir en el frontend.
const SEEDS = join(__dirname, '../../../api/app/Database/Seeds');
const php = readdirSync(SEEDS)
  .filter((f) => f.endsWith('.php'))
  .map((f) => readFileSync(join(SEEDS, f), 'utf8'))
  .join('\n');
const claves = (campo: string) => [...php.matchAll(new RegExp(`'${campo}'\\s*=>\\s*'([^']+)'`, 'g'))].map((m) => m[1]);

describe('Catálogo único de íconos y avatares', () => {
  it('los seeders solo usan íconos del catálogo', () => {
    const usados = claves('icono');
    expect(usados.length).toBeGreaterThan(0);
    expect(usados.filter((c) => !(c in ICONOS))).toEqual([]);
  });

  it('los seeders solo usan avatares del catálogo', () => {
    const usados = claves('avatar');
    expect(usados.length).toBeGreaterThan(0);
    expect(usados.filter((c) => !(c in AVATARES))).toEqual([]);
  });
});
