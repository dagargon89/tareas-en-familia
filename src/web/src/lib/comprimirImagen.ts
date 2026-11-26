/**
 * Comprime y redimensiona imágenes grandes en el cliente antes de subirlas,
 * evitando rechazos por el límite de 3 MB en fotos modernas de celular.
 * Si el entorno no soporta canvas/createImageBitmap o la imagen ya es ligera, devuelve el original.
 */
export async function comprimirImagen(archivo: File, maxLado = 1600, maxBytes = 1.5 * 1024 * 1024): Promise<File> {
  if (archivo.size <= maxBytes || typeof document === 'undefined' || typeof URL.createObjectURL !== 'function') {
    return archivo;
  }

  try {
    const url = URL.createObjectURL(archivo);
    try {
      const img = await new Promise<HTMLImageElement>((resolve, reject) => {
        const el = new Image();
        el.onload = () => resolve(el);
        el.onerror = reject;
        el.src = url;
      });

      const escala = Math.min(1, maxLado / Math.max(img.naturalWidth || 1, img.naturalHeight || 1));
      const ancho = Math.max(1, Math.round(img.naturalWidth * escala));
      const alto = Math.max(1, Math.round(img.naturalHeight * escala));

      const canvas = document.createElement('canvas');
      canvas.width = ancho;
      canvas.height = alto;
      const ctx = canvas.getContext('2d');
      if (!ctx) return archivo;
      ctx.drawImage(img, 0, 0, ancho, alto);

      const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.82));
      if (!blob || blob.size >= archivo.size) return archivo;

      const nombre = archivo.name.replace(/\.[^.]+$/, '') + '.jpg';
      return new File([blob], nombre, { type: 'image/jpeg', lastModified: Date.now() });
    } finally {
      URL.revokeObjectURL(url);
    }
  } catch {
    return archivo;
  }
}
