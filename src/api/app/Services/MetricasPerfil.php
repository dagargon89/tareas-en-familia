<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Métricas de un perfil para evaluar las reglas de `insignias.regla` (doc 03 §5).
 * Cada métrica se consulta una sola vez y solo si alguna regla la necesita.
 */
final class MetricasPerfil
{
    private ?int $aprobadas = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $porTipo = null;

    private ?int $racha = null;

    public function __construct(private readonly BaseConnection $db, private readonly int $perfilId)
    {
    }

    /**
     * @param array<string, mixed> $regla `{ tipo, min, tipo_tarea? }`
     */
    public function cumple(array $regla): bool
    {
        $minimo = $regla['min'] ?? null;

        if (! is_int($minimo) || $minimo < 1) {
            return false;
        }

        return match ($regla['tipo'] ?? null) {
            'tareas_aprobadas' => $this->aprobadas() >= $minimo,
            'tareas_por_tipo'  => ($this->porTipo()[$regla['tipo_tarea'] ?? ''] ?? 0) >= $minimo,
            'racha_semanas'    => $this->racha() >= $minimo,
            default            => false,
        };
    }

    public function aprobadas(): int
    {
        return $this->aprobadas ??= array_sum($this->porTipo());
    }

    /**
     * Instancias aprobadas del perfil por tipo de tarea.
     *
     * @return array<string, int>
     */
    public function porTipo(): array
    {
        if ($this->porTipo === null) {
            $filas = $this->db->table('tarea_instancias i')
                ->select('t.tipo, COUNT(*) AS total')
                ->join('tareas t', 't.id = i.tarea_id')
                ->where(['i.perfil_id' => $this->perfilId, 'i.estado' => 'aprobada'])
                ->groupBy('t.tipo')
                ->get()->getResultArray();

            $this->porTipo = array_map('intval', array_column($filas, 'total', 'tipo'));
        }

        return $this->porTipo;
    }

    public function racha(): int
    {
        return $this->racha ??= (new ProgresoService($this->db))->rachaSemanas($this->perfilId);
    }
}
