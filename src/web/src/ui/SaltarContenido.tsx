/** Enlace accesible para usuarios de teclado: salta directo al <main id="contenido-principal">. */
export function SaltarContenido() {
  return (
    <a
      href="#contenido-principal"
      className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-boton focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-etiqueta focus:text-blanco"
    >
      Saltar al contenido principal
    </a>
  );
}
