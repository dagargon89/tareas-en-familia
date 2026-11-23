<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Calendario;
use App\Domain\Rachas;
use App\Domain\SemanaIso;
use App\Entities\Perfil;
use App\Exceptions\ApiException;
use App\Models\PerfilModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;

/**
 * Panel del padre (RF-16): E-45. Por hija activa, en el orden de los perfiles y nunca ordenadas
 * por desempeño (RN-02): porcentaje de la semana, por aprobar, racha, disponible y próximas entregas.
 */
final class PanelService
{
    public const MAX_ENTREGAS = 5;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param mixed $semanaIso `?semana=AAAA-Www`; sin valor, la semana en curso
     *
     * @return array{semana_iso: string, hijas: list<array<string, mixed>>, meta_familiar: array<string, mixed>|null, canjes_pendientes: list<array<string, mixed>>}
     */
    public function panel(mixed $semanaIso): array
    {
        $hoy    = Calendario::hoy(Time::now());
        $semana = $this->semana($semanaIso, $hoy);

        $puntos   = new PuntosService($this->db);
        $progreso = new ProgresoService($this->db);
        $semanal  = $this->porcentajes($semana);
        $hechas   = $this->porAprobar();
        $hijas    = [];

        foreach (model(PerfilModel::class)->activosOrdenados() as $perfil) {
            /** @var Perfil $perfil */
            [$asignadas, $aprobadas] = $semanal[$perfil->id] ?? [0, 0];
            $porcentaje              = Rachas::porcentaje($asignadas, $aprobadas);

            $hijas[] = [
                'perfil'            => $perfil->publico(),
                'porcentaje_semana' => $porcentaje == (int) $porcentaje ? (int) $porcentaje : $porcentaje,
                'por_aprobar'       => $hechas[$perfil->id] ?? 0,
                'racha_semanas'     => $progreso->rachaSemanas($perfil->id),
                'disponible'        => $puntos->saldos($perfil->id)['disponible'],
                'proximas_entregas' => $this->proximasEntregas($perfil->id, $hoy),
            ];
        }

        return [
            'semana_iso'        => $semana->codigo(),
            'hijas'             => $hijas,
            'meta_familiar'     => (new MetasService($this->db))->activa(),
            'canjes_pendientes' => (new CanjesService($this->db))->admin('solicitado'),
        ];
    }

    private function semana(mixed $semanaIso, string $hoy): SemanaIso
    {
        if ($semanaIso === null || $semanaIso === '') {
            return SemanaIso::desdeFecha($hoy);
        }

        try {
            return SemanaIso::desdeCodigo(is_string($semanaIso) ? $semanaIso : '');
        } catch (InvalidArgumentException) {
            throw ApiException::validacion(['semana' => 'Elige una semana con el formato 2026-W48.']);
        }
    }

    /**
     * Instancias asignadas y aprobadas por perfil en la semana.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function porcentajes(SemanaIso $semana): array
    {
        $filas = $this->db->table('tarea_instancias')
            ->select("perfil_id, COUNT(*) AS asignadas, COALESCE(SUM(estado = 'aprobada'), 0) AS aprobadas", false)
            ->where('fecha >=', $semana->lunes())
            ->where('fecha <=', $semana->domingo())
            ->groupBy('perfil_id')
            ->get()->getResultArray();

        $porPerfil = [];

        foreach ($filas as $fila) {
            $porPerfil[(int) $fila['perfil_id']] = [(int) $fila['asignadas'], (int) $fila['aprobadas']];
        }

        return $porPerfil;
    }

    /**
     * Instancias `hecha` (esperando al padre) por perfil, de cualquier fecha.
     *
     * @return array<int, int>
     */
    private function porAprobar(): array
    {
        $filas = $this->db->table('tarea_instancias')
            ->select('perfil_id, COUNT(*) AS total')
            ->where('estado', 'hecha')
            ->groupBy('perfil_id')
            ->get()->getResultArray();

        return array_map('intval', array_column($filas, 'total', 'perfil_id'));
    }

    /**
     * Escolares de tareas activas con entrega desde hoy y sin aprobar, la más próxima primero (RF-16, RF-19).
     *
     * @return list<array<string, mixed>>
     */
    private function proximasEntregas(int $perfilId, string $hoy): array
    {
        $filas = (new InstanciasService($this->db))->consulta()
            ->where('i.perfil_id', $perfilId)
            ->where('t.tipo', 'escolar')
            ->where('t.activa', 1)
            ->where('i.estado !=', 'aprobada')
            ->where('i.fecha_entrega >=', $hoy)
            ->orderBy('i.fecha_entrega')
            ->orderBy('i.id')
            ->limit(self::MAX_ENTREGAS)
            ->get()->getResultArray();

        return array_map(InstanciasService::aInstancia(...), $filas);
    }
}
