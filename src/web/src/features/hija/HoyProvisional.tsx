import { useSesion } from '../../hooks/useSesion.ts';
import { fechaLarga, fechaLocal } from '../../lib/fechas.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';

/** "Mi día" mínimo del S1: saludo con su nombre (CP-009). La lista de tareas llega en el S2. */
export function HoyProvisional() {
  const { data } = useSesion();
  const nivel = useSesionStore((s) => s.nivel);
  return (
    <section className="flex flex-col gap-2">
      <h1 className="m-0 text-display">¡Hola, {data?.perfil_activo?.apodo}!</h1>
      <p className="m-0 text-cuerpo capitalize text-tinta-600">{fechaLarga(fechaLocal())}</p>
      <p className="m-0 mt-6 rounded-tarjeta bg-superficie p-6 text-cuerpo">{VOCABULARIO[nivel].diaVacio}</p>
    </section>
  );
}
