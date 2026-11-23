<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Racha;
use App\Domain\SemanaIso;
use App\Models\ConfiguracionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Cierre semanal de rachas (RF-10, doc 03 §4.1 y §9). Nunca resta puntos (RN-01).
 */
final class RachasService
{
    public const UMBRAL_POR_OMISION = 80;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Para cada perfil activo con instancias en la semana: inserta su fila de `rachas`
     * con el umbral vigente y evalúa sus insignias, en una transacción por perfil.
     * Sin instancias no hay fila. Es idempotente (índice único perfil + semana).
     *
     * @return int Filas nuevas
     */
    public function cerrarSemana(SemanaIso $semana): int
    {
        $umbral    = model(ConfiguracionModel::class)->entero('racha_umbral', self::UMBRAL_POR_OMISION);
        $insignias = new InsigniasService($this->db);
        $nuevas    = 0;

        $conteos = $this->db->table('tarea_instancias i')
            ->select("i.perfil_id, COUNT(*) AS asignadas, SUM(i.estado = 'aprobada') AS aprobadas", false)
            ->join('perfiles p', 'p.id = i.perfil_id')
            ->where('p.activo', 1)
            ->where('i.fecha >=', $semana->lunes())
            ->where('i.fecha <=', $semana->domingo())
            ->groupBy('i.perfil_id')
            ->get()->getResultArray();

        foreach ($conteos as $c) {
            $perfilId  = (int) $c['perfil_id'];
            $asignadas = (int) $c['asignadas'];
            $aprobadas = (int) $c['aprobadas'];

            $this->db->transException(true)->transStart();
            $this->db->table('rachas')->ignore(true)->insert([
                'perfil_id'  => $perfilId,
                'semana_iso' => $semana->codigo(),
                'asignadas'  => $asignadas,
                'aprobadas'  => $aprobadas,
                'porcentaje' => Racha::porcentaje($asignadas, $aprobadas),
                'cumplida'   => Racha::semanaCumplida($asignadas, $aprobadas, $umbral) ? 1 : 0,
                'created_at' => Time::now()->toDateTimeString(),
            ]);

            if ($this->db->affectedRows() === 1) {
                $nuevas++;
                $insignias->evaluar($perfilId);
            }

            $this->db->transComplete();
        }

        return $nuevas;
    }
}
