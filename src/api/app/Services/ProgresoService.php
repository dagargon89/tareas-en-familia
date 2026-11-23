<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Calendario;
use App\Domain\Niveles;
use App\Domain\SemanaIso;
use App\Libraries\Fechas;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * E-13 `GET /yo/progreso`: forma `Progreso` del contrato (doc 03 §4).
 */
final class ProgresoService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function progreso(int $perfilId): array
    {
        $saldos = (new PuntosService($this->db))->saldos($perfilId);

        return [
            'xp'              => $saldos['xp'],
            'saldo'           => $saldos['saldo'],
            'disponible'      => $saldos['disponible'],
            'nivel'           => Niveles::para($saldos['xp']),
            'siguiente_nivel' => Niveles::siguiente($saldos['xp']),
            'racha_semanas'   => $this->rachaSemanas($perfilId),
            'semana_actual'   => $this->semanaActual($perfilId),
            'insignias'       => $this->insignias($perfilId),
            'meta_familiar'   => $this->metaFamiliar(),
        ];
    }

    /**
     * Semanas `cumplida = 1` consecutivas desde la última semana cerrada hacia atrás;
     * las semanas sin registro (sin tareas asignadas) se saltan (doc 03 §4).
     */
    public function rachaSemanas(int $perfilId): int
    {
        $actual = SemanaIso::desdeFecha(Calendario::hoy(Time::now()))->codigo();
        $filas  = $this->db->table('rachas')
            ->select('cumplida')
            ->where('perfil_id', $perfilId)
            ->where('semana_iso <', $actual)
            ->orderBy('semana_iso', 'DESC')
            ->get()->getResultArray();

        $racha = 0;

        foreach ($filas as $fila) {
            if ((int) $fila['cumplida'] !== 1) {
                break;
            }

            $racha++;
        }

        return $racha;
    }

    /**
     * Semana ISO en curso: instancias asignadas, aprobadas y porcentaje (2 decimales).
     *
     * @return array{asignadas: int, aprobadas: int, porcentaje: float|int}
     */
    public function semanaActual(int $perfilId): array
    {
        $semana = SemanaIso::desdeFecha(Calendario::hoy(Time::now()));
        $fila   = $this->db->table('tarea_instancias')
            ->select("COUNT(*) AS asignadas, COALESCE(SUM(estado = 'aprobada'), 0) AS aprobadas", false)
            ->where('perfil_id', $perfilId)
            ->where('fecha >=', $semana->lunes())
            ->where('fecha <=', $semana->domingo())
            ->get()->getRowArray();

        $asignadas  = (int) ($fila['asignadas'] ?? 0);
        $aprobadas  = (int) ($fila['aprobadas'] ?? 0);
        $porcentaje = $asignadas === 0 ? 0 : round($aprobadas * 100 / $asignadas, 2);

        return [
            'asignadas'  => $asignadas,
            'aprobadas'  => $aprobadas,
            'porcentaje' => $porcentaje == (int) $porcentaje ? (int) $porcentaje : $porcentaje,
        ];
    }

    /**
     * Catálogo completo de insignias con `obtenida_en` del perfil (o null).
     *
     * @return list<array{codigo: string, nombre: string, descripcion: string, icono: string, color: string, obtenida_en: string|null}>
     */
    public function insignias(int $perfilId): array
    {
        $obtenidas = array_column(
            $this->db->table('perfil_insignias')->select('insignia_id, obtenida_en')->where('perfil_id', $perfilId)->get()->getResultArray(),
            'obtenida_en',
            'insignia_id',
        );
        $filas = $this->db->table('insignias')->orderBy('orden')->orderBy('id')->get()->getResultArray();

        return array_map(static fn (array $f): array => [
            'codigo'      => $f['codigo'],
            'nombre'      => $f['nombre'],
            'descripcion' => $f['descripcion'],
            'icono'       => $f['icono'],
            'color'       => $f['color'],
            'obtenida_en' => Fechas::iso($obtenidas[$f['id']] ?? null),
        ], $filas);
    }

    /**
     * Meta activa con `progreso_puntos` = movimientos de origen `tarea` de todos los perfiles
     * desde `fecha_inicio` (doc 03 §4), o null.
     *
     * @return array<string, mixed>|null
     */
    public function metaFamiliar(): ?array
    {
        $meta = $this->db->table('metas_familiares')->where('estado', 'activa')->get()->getRowArray();

        if ($meta === null) {
            return null;
        }

        $progreso = $this->db->table('movimientos_puntos')
            ->selectSum('puntos', 'total')
            ->where('origen', 'tarea')
            ->where('created_at >=', $meta['fecha_inicio'] . ' 00:00:00')
            ->get()->getRowArray();

        return [
            'id'              => (int) $meta['id'],
            'titulo'          => $meta['titulo'],
            'premio'          => $meta['premio'],
            'objetivo_puntos' => (int) $meta['objetivo_puntos'],
            'progreso_puntos' => (int) ($progreso['total'] ?? 0),
            'fecha_inicio'    => $meta['fecha_inicio'],
            'fecha_limite'    => $meta['fecha_limite'],
            'estado'          => $meta['estado'],
        ];
    }
}
