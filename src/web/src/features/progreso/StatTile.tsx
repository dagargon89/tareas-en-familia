/** Tarjeta de color con etiqueta y número grande (doc 08 §4). */
export function StatTile({ etiqueta, valor, tono = 'ambar' }: { etiqueta: string; valor: string | number; tono?: 'ambar' | 'menta' | 'rosa' | 'lavanda' }) {
  const fondo = { ambar: 'bg-ambar-200', menta: 'bg-menta-200', rosa: 'bg-rosa-200', lavanda: 'bg-lavanda-100' }[tono];
  return (
    <div className={`flex flex-col gap-1 rounded-tarjeta-interna p-4 text-tinta-900 ${fondo}`}>
      <span className="text-etiqueta">{etiqueta}</span>
      <span className="text-display">{valor}</span>
    </div>
  );
}
