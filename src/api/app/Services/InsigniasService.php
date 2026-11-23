<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Fechas;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Insignias (RF-11, doc 03 §5). Evalúa las reglas que el perfil aún no tiene; corre
 * dentro de la transacción de aprobar y del cierre semanal. Cada insignia se otorga
 * una sola vez (índice único) y acredita su XP con `PuntosService`.
 */
final class InsigniasService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Otorga las insignias cuyo requisito ya se cumple.
     *
     * @return list<array{codigo: string, nombre: string, descripcion: string, icono: string, color: string, obtenida_en: string|null}>
     */
    public function evaluar(int $perfilId): array
    {
        $pendientes = $this->db->table('insignias')
            ->whereNotIn('id', static fn ($q) => $q->select('insignia_id')->from('perfil_insignias')->where('perfil_id', $perfilId))
            ->orderBy('orden')->orderBy('id')
            ->get()->getResultArray();

        $nuevas = [];
        $puntos = new PuntosService($this->db);

        foreach ($pendientes as $insignia) {
            $regla = json_decode((string) $insignia['regla'], true);

            if (! is_array($regla) || ! $this->cumple($perfilId, $regla)) {
                continue;
            }

            $ahora = Time::now()->toDateTimeString();
            $this->db->table('perfil_insignias')->ignore(true)->insert([
                'perfil_id' => $perfilId, 'insignia_id' => (int) $insignia['id'], 'obtenida_en' => $ahora,
            ]);

            if ($this->db->affectedRows() !== 1) {
                continue; // otra transacción la otorgó primero
            }

            $puntos->acreditarInsignia($perfilId, (int) $this->db->insertID(), (int) $insignia['xp'], (string) $insignia['nombre']);
            $nuevas[] = [
                'codigo'      => $insignia['codigo'],
                'nombre'      => $insignia['nombre'],
                'descripcion' => $insignia['descripcion'],
                'icono'       => $insignia['icono'],
                'color'       => $insignia['color'],
                'obtenida_en' => Fechas::iso($ahora),
            ];
        }

        return $nuevas;
    }

    /**
     * @param array<string, mixed> $regla `{ tipo, min, tipo_tarea? }`
     */
    private function cumple(int $perfilId, array $regla): bool
    {
        $minimo = (int) ($regla['min'] ?? PHP_INT_MAX);

        return match ($regla['tipo'] ?? null) {
            'tareas_aprobadas' => $this->aprobadas($perfilId) >= $minimo,
            'tareas_por_tipo'  => $this->aprobadas($perfilId, (string) ($regla['tipo_tarea'] ?? '')) >= $minimo,
            'racha_semanas'    => (new ProgresoService($this->db))->rachaSemanas($perfilId) >= $minimo,
            default            => false,
        };
    }

    private function aprobadas(int $perfilId, ?string $tipo = null): int
    {
        $q = $this->db->table('tarea_instancias i')->where('i.perfil_id', $perfilId)->where('i.estado', 'aprobada');

        if ($tipo !== null) {
            $q->join('tareas t', 't.id = i.tarea_id')->where('t.tipo', $tipo);
        }

        return $q->countAllResults();
    }
}
