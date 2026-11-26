import { CircleCheck, Clock, PartyPopper, Undo2 } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import type { Canje, EstadoCanje, NivelUx, RecompensaParaPerfil } from '../../api/ApiClient.ts';
import { useMisCanjes, useRecompensasPerfil, useSolicitarCanje } from '../../hooks/usePremios.ts';
import { useProgreso } from '../../hooks/useProgreso.ts';
import { detalleError } from '../../lib/errores.ts';
import { fechaCorta } from '../../lib/fechas.ts';
import { sonar } from '../../lib/sonidos.ts';
import { VOCABULARIO } from '../../niveles/vocabulario.ts';
import { useSesionStore } from '../../stores/sesionStore.ts';
import { SkeletonRejilla } from '../../ui/Cargando.tsx';
import { DialogoConfirmacion } from '../../ui/DialogoConfirmacion.tsx';
import { EstadoError } from '../../ui/EstadoError.tsx';
import { Toast } from '../../ui/Toast.tsx';
import { StatTile } from '../progreso/StatTile.tsx';
import { RewardCard } from './RewardCard.tsx';

/** Cómo se ve cada estado de un canje para la hija: siempre en tono amable (RN-01). */
const ESTADO_CANJE: Record<EstadoCanje, { texto: string; Icono: typeof Clock; fondo: string }> = {
  solicitado: { texto: 'Papá o mamá lo están revisando', Icono: Clock, fondo: 'bg-lavanda-100 text-indigo-700' },
  aprobado: { texto: '¡Aprobado! Pronto lo recibes', Icono: CircleCheck, fondo: 'bg-menta-200' },
  entregado: { texto: '¡Disfrútalo!', Icono: PartyPopper, fondo: 'bg-ambar-200' },
  rechazado: { texto: 'Esta vez no. Tus puntos regresaron', Icono: Undo2, fondo: 'bg-superficie text-tinta-600' },
};

interface VistaProps {
  recompensas: RecompensaParaPerfil[];
  canjes: Canje[];
  disponible: number | null;
  nivel: NivelUx;
  pidiendo: number | null;
  aviso: string | null;
  onCerrarAviso?: () => void;
  onCanjear: (r: RecompensaParaPerfil) => void;
}

function Lista({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-3">
      <h2 className="m-0 text-titulo">{titulo}</h2>
      {children}
    </div>
  );
}

/** Premios de la hija (RF-09): lo que ya le alcanza primero, y sus canjes. */
export function PremiosVista({ recompensas, canjes, disponible, nivel, pidiendo, aviso, onCerrarAviso, onCanjear }: VistaProps) {
  const v = VOCABULARIO[nivel];
  const pendientes = new Set(canjes.filter((c) => c.estado === 'solicitado').map((c) => c.recompensa.id));
  const ordenadas = [...recompensas].sort((a, b) => Number(b.alcanza) - Number(a.alcanza) || a.costo - b.costo);
  const recientes = [...canjes].sort((a, b) => b.created_at.localeCompare(a.created_at)).slice(0, 10);

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <h1 className="m-0 text-display">Premios</h1>
        {disponible !== null && (
          <div className="min-w-40">
            <StatTile etiqueta={`${v.puntos} para canjear`} valor={disponible} tono="ambar" />
          </div>
        )}
      </div>

      <Toast mensaje={aviso} sobreNav onCerrar={onCerrarAviso} />

      {ordenadas.length === 0 ? (
        <p className="m-0 rounded-tarjeta bg-superficie p-6 text-cuerpo text-tinta-600">Todavía no hay premios. Pídele a papá o mamá que agreguen algunos.</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {ordenadas.map((r) => (
            <RewardCard
              key={r.id}
              recompensa={r}
              nivel={nivel}
              solicitada={pendientes.has(r.id) || pidiendo === r.id}
              onCanjear={onCanjear}
            />
          ))}
        </div>
      )}

      {recientes.length > 0 && (
        <Lista titulo="Mis premios pedidos">
          <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {recientes.map((c) => {
              const e = ESTADO_CANJE[c.estado];
              return (
                <li key={c.id} className={`flex items-center gap-3 rounded-tarjeta-interna p-3 ${e.fondo}`}>
                  <e.Icono className="size-5 shrink-0" aria-hidden="true" />
                  <div className="flex min-w-0 flex-1 flex-col">
                    <span className="text-cuerpo">{c.recompensa.titulo}</span>
                    <span className="text-etiqueta opacity-80">{e.texto}</span>
                  </div>
                  <span className="text-etiqueta opacity-80">{fechaCorta(c.created_at.slice(0, 10))}</span>
                </li>
              );
            })}
          </ul>
        </Lista>
      )}
    </section>
  );
}

export function Premios() {
  const nivel = useSesionStore((s) => s.nivel);
  const sonidos = useSesionStore((s) => s.sonidos);
  const v = VOCABULARIO[nivel];
  const recompensas = useRecompensasPerfil();
  const canjes = useMisCanjes();
  const progreso = useProgreso();
  const solicitar = useSolicitarCanje();
  const [aviso, setAviso] = useState<string | null>(null);
  const [porConfirmar, setPorConfirmar] = useState<RecompensaParaPerfil | null>(null);

  if (recompensas.isPending || canjes.isPending) return <SkeletonRejilla tarjetas={4} texto="Cargando premios…" />;
  if (recompensas.isError || canjes.isError) {
    return (
      <EstadoError
        mensaje="No pudimos cargar los premios. Intenta de nuevo."
        onReintentar={() => {
          void recompensas.refetch();
          void canjes.refetch();
        }}
      />
    );
  }

  const disponible = progreso.data?.disponible ?? null;

  return (
    <>
      <PremiosVista
        recompensas={recompensas.data}
        canjes={canjes.data}
        disponible={disponible}
        nivel={nivel}
        pidiendo={solicitar.isPending ? (solicitar.variables ?? null) : null}
        aviso={aviso}
        onCerrarAviso={() => setAviso(null)}
        onCanjear={(r) => setPorConfirmar(r)}
      />
      <DialogoConfirmacion
        abierto={porConfirmar !== null}
        titulo={porConfirmar ? `¿Pedir "${porConfirmar.titulo}"?` : ''}
        mensaje={
          porConfirmar
            ? `Cuesta ${porConfirmar.costo} ${v.unidadPuntos}.${disponible !== null ? ` Te quedarían ${Math.max(0, disponible - porConfirmar.costo)} ${v.unidadPuntos}.` : ''}`
            : ''
        }
        textoConfirmar="Sí, pedir premio"
        textoCancelar="Todavía no"
        ocupado={solicitar.isPending}
        onCancelar={() => setPorConfirmar(null)}
        onConfirmar={() => {
          if (!porConfirmar) return;
          const elegido = porConfirmar;
          setPorConfirmar(null);
          setAviso(null);
          solicitar.mutate(elegido.id, {
            onSuccess: () => {
              sonar('canje', sonidos);
              setAviso(`¡Listo! Pediste "${elegido.titulo}". Papá o mamá lo revisan.`);
            },
            onError: (e) =>
              setAviso(detalleError(e).codigo === 'SALDO_INSUFICIENTE' ? 'Todavía no te alcanza. ¡Sigue con tus misiones!' : 'No se pudo pedir. Intenta otra vez.'),
          });
        }}
      />
    </>
  );
}
