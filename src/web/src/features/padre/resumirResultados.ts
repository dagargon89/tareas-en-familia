import type { InstanciaRevision, ResultadoRevision } from '../../api/ApiClient.ts';

/** Resumen amable de lo que pasó al aprobar (puntos, insignias, meta). */
export function resumirResultados(resultados: ResultadoRevision[], items: InstanciaRevision[]): string {
  const aprobadas = resultados.filter((r) => r.instancia.estado === 'aprobada');
  if (aprobadas.length === 0) return 'Listo.';
  const porHija = new Map<string, number>();
  for (const r of aprobadas) {
    const apodo = items.find((i) => i.id === r.instancia.id)?.perfil.apodo ?? 'su perfil';
    porHija.set(apodo, (porHija.get(apodo) ?? 0) + r.puntos_acreditados);
  }
  const partes = [...porHija].map(([apodo, pts]) => `+${pts} a ${apodo}`);
  const insignias = aprobadas.flatMap((r) => r.insignias_nuevas.map((i) => i.nombre));
  let texto = `Aprobado: ${partes.join(', ')}.`;
  if (insignias.length) texto += ` Nuevas insignias: ${insignias.join(', ')}.`;
  if (aprobadas.some((r) => r.meta_cumplida)) texto += ' ¡Cumplieron la meta familiar!';
  return texto;
}
