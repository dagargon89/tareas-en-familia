/**
 * Reglas de PIN del doc 04 §3.1: 4 dígitos, sin repetidos (0000…9999) ni secuencias
 * (1234, 4321, 0123…). El servidor es la autoridad; esto solo adelanta el aviso en el formulario.
 */
export function problemaPin(pin: string): string | null {
  if (!/^\d{4}$/.test(pin)) return 'El PIN debe tener 4 dígitos';
  const d = [...pin].map(Number);
  if (d.every((x) => x === d[0])) return 'Evita repetir el mismo dígito';
  const sube = d.every((x, i) => i === 0 || x === d[i - 1] + 1);
  const baja = d.every((x, i) => i === 0 || x === d[i - 1] - 1);
  if (sube || baja) return 'Evita secuencias como 1234 o 4321';
  return null;
}
