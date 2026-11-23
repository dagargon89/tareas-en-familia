import { Link } from 'react-router';

/** Marcador de las pantallas que aún no tienen su sprint. */
export function EnConstruccion({ pantalla, sprint }: { pantalla: string; sprint: string }) {
  return (
    <main className="mx-auto flex min-h-dvh max-w-xl flex-col items-center justify-center gap-4 p-6 text-center">
      <h1 className="m-0 text-display">{pantalla}</h1>
      <p className="m-0 text-cuerpo text-tinta-600">Se construye en el {sprint}.</p>
      {import.meta.env.DEV && (
        <Link to="/galeria" className="text-cuerpo text-indigo-700 underline">
          Ver la galería de componentes
        </Link>
      )}
    </main>
  );
}
