<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Rachas;
use App\Domain\SemanaIso;
use App\Models\ConfiguracionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Cierre semanal (RF-10, doc 03 §9): una fila de `rachas` por perfil con instancias en la
 * semana y evaluación de insignias de racha. Nunca crea movimientos negativos (RN-01).
 */
final class RachasService
{
    public const UMBRAL_POR_OMISION = 80;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Cierra la semana con el `racha_umbral` vigente al momento del cierre (CP-086).
     * Idempotente: una semana ya cerrada para un perfil no se vuelve a escribir.
     *
     * @return int Filas nuevas en `rachas`
     */
    public function cerrarSemana(SemanaIso $semana): int
    {
        $umbral = model(ConfiguracionModel::class)->entero('racha_umbral', self::UMBRAL_POR_OMISION);
        $filas  = $this->db->table('tarea_instancias')
            ->select("perfil_id, COUNT(*) AS asignadas, COALESCE(SUM(estado = 'aprobada'), 0) AS aprobadas", false)
            ->where('fecha >=', $semana->lunes())
            ->where('fecha <=', $semana->domingo())
            ->groupBy('perfil_id')
            ->orderBy('perfil_id')
            ->get()->getResultArray();

        $nuevas     = 0;
        $insignias  = new InsigniasService($this->db);

        foreach ($filas as $fila) {
            $asignadas = (int) $fila['asignadas'];
            $aprobadas = (int) $fila['aprobadas'];
            $cumplida  = Rachas::cumplida($asignadas, $aprobadas, $umbral);

            if ($cumplida === null) {
                continue;
            }

            $this->db->transBegin();

            try {
                $this->db->table('rachas')->ignore(true)->insert([
                    'perfil_id'  => (int) $fila['perfil_id'],
                    'semana_iso' => $semana->codigo(),
                    'asignadas'  => $asignadas,
                    'aprobadas'  => $aprobadas,
                    'porcentaje' => Rachas::porcentaje($asignadas, $aprobadas),
                    'cumplida'   => $cumplida ? 1 : 0,
                    'created_at' => Time::now()->toDateTimeString(),
                ]);

                if ($this->db->affectedRows() === 1) {
                    $nuevas++;
                    $insignias->evaluar((int) $fila['perfil_id']);
                }

                $this->db->transCommit();
            } catch (\Throwable $e) {
                $this->db->transRollback();

                throw $e;
            }
        }

        return $nuevas;
    }
}
