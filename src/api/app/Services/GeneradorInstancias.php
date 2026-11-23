<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Calendario;
use App\Domain\ReglaRecurrencia;
use App\Models\ConfiguracionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Genera `tarea_instancias` desde las reglas de recurrencia (RF-03, RN-07).
 * `INSERT IGNORE` sobre `uq_instancias_tarea_fecha`: generar dos veces no duplica.
 */
final class GeneradorInstancias
{
    public const DIAS_POR_OMISION = 7;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Instancias de `[desde, hasta]` para las tareas activas de perfiles activos.
     *
     * @return int Instancias nuevas
     */
    public function generar(string $desde, string $hasta, ?int $tareaId = null): int
    {
        $consulta = $this->db->table('tareas t')
            ->select('t.id, t.perfil_id, t.puntos, t.fecha_entrega, r.frecuencia, r.dias_semana, r.hora, r.fecha_inicio, r.fecha_fin')
            ->join('tarea_recurrencias r', 'r.tarea_id = t.id')
            ->join('perfiles p', 'p.id = t.perfil_id')
            ->where('t.activa', 1)
            ->where('p.activo', 1)
            ->where('r.fecha_inicio <=', $hasta)
            ->groupStart()->where('r.fecha_fin', null)->orWhere('r.fecha_fin >=', $desde)->groupEnd();

        if ($tareaId !== null) {
            $consulta->where('t.id', $tareaId);
        }

        $ahora = Time::now()->toDateTimeString();
        $filas = [];

        foreach ($consulta->get()->getResultArray() as $tarea) {
            foreach (ReglaRecurrencia::desdeFila($tarea)->fechasEntre($desde, $hasta) as $fecha) {
                $filas[] = [
                    'tarea_id'      => (int) $tarea['id'],
                    'perfil_id'     => (int) $tarea['perfil_id'],
                    'fecha'         => $fecha,
                    'hora'          => $tarea['hora'],
                    'fecha_entrega' => $tarea['fecha_entrega'],
                    'puntos'        => (int) $tarea['puntos'],
                    'estado'        => 'pendiente',
                    'created_at'    => $ahora,
                    'updated_at'    => $ahora,
                ];
            }
        }

        if ($filas === []) {
            return 0;
        }

        return (int) $this->db->table('tarea_instancias')->ignore(true)->insertBatch($filas);
    }

    /**
     * Cubre un cron retrasado: asegura las instancias de la fecha pedida (`GET /yo/dia`).
     */
    public function asegurar(string $fecha): int
    {
        return $this->generar($fecha, $fecha);
    }

    /**
     * Hoy + `configuracion.dias_generacion` días (subtarea "generar" del cron).
     */
    public function generarProximos(string $hoy, ?int $tareaId = null): int
    {
        return $this->generar($hoy, Calendario::sumarDias($hoy, $this->diasGeneracion()), $tareaId);
    }

    public function diasGeneracion(): int
    {
        return max(0, model(ConfiguracionModel::class)->entero('dias_generacion', self::DIAS_POR_OMISION));
    }
}
