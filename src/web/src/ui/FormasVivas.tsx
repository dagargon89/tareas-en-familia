/**
 * Formas decorativas (BlobShapes, doc 08 §4): flor ámbar, píldora rosa y gota menta.
 * Exploradoras: flotan. Aventureras: estáticas. Pro: ocultas. Con movimiento reducido no flotan.
 */
export function FormasVivas() {
  return (
    <div aria-hidden="true" className="formas-vivas pointer-events-none fixed inset-0 -z-10 overflow-hidden">
      <svg className="forma forma-flor" viewBox="0 0 100 100" width="160" height="160">
        <g fill="var(--tf-ambar-200)">
          <circle cx="50" cy="26" r="22" />
          <circle cx="74" cy="50" r="22" />
          <circle cx="50" cy="74" r="22" />
          <circle cx="26" cy="50" r="22" />
          <circle cx="50" cy="50" r="20" />
        </g>
      </svg>
      <svg className="forma forma-pildora" viewBox="0 0 120 50" width="180" height="75">
        <rect width="120" height="50" rx="25" fill="var(--tf-rosa-200)" />
      </svg>
      <svg className="forma forma-gota" viewBox="0 0 100 120" width="110" height="132">
        <path d="M50 0C50 0 100 55 100 80a50 50 0 0 1-100 0C0 55 50 0 50 0Z" fill="var(--tf-menta-200)" />
      </svg>
    </div>
  );
}
