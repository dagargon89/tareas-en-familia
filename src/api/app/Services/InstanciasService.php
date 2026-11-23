<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Calendario;
use App\Domain\ReglaRecurrencia;
use App\Exceptions\ApiException;
use App\Libraries\Fechas;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * "Mi día" (RF-04, RF-13: E-11) y marcar hecha (RF-05: E-12).
 */
final class InstanciasService
{
    /**
     * Días hacia atrás y hacia adelante que se pueden consultar (RF-04).
     */
    public const RANGO_DIAS = 7;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    private function hoy(): string
    {
        return Calendario::hoy(Time::now());
    }

    /**
     * E-11 · `Dia` del perfil. Incluye las instancias de la fecha y las escolares
     * vigentes (`fecha < D <= fecha_entrega`, no aprobadas) del doc 03 §4.1.
     *
     * @return array{fecha: string, es_hoy: bool, instancias: list<array<string, mixed>>, resumen: array{total: int, hechas: int, aprobadas: int}}
     */
    public function dia(int $perfilId, mixed $fecha): array
    {
        $hoy   = $this->hoy();
        $fecha = $fecha === null || $fecha === '' ? $hoy : $fecha;

        if (! is_string($fecha) || ! ReglaRecurrencia::esFechaValida($fecha) || abs(Calendario::diasEntre($hoy, $fecha)) > self::RANGO_DIAS) {
            throw ApiException::validacion(['fecha' => 'Elige un día entre una semana antes y una semana después de hoy.']);
        }

        (new GeneradorInstancias($this->db))->asegurar($fecha);

        $filas = $this->consulta()
            ->where('i.perfil_id', $perfilId)
            ->groupStart()
                ->where('i.fecha', $fecha)
                ->orGroupStart()
                    ->where('t.tipo', 'escolar')
                    ->where('t.activa', 1)
                    ->where('i.fecha <', $fecha)
                    ->where('i.fecha_entrega >=', $fecha)
                    ->where('i.estado !=', 'aprobada')
                ->groupEnd()
            ->groupEnd()
            ->orderBy('i.hora IS NULL', '', false)
            ->orderBy('i.hora')
            ->orderBy('i.id')
            ->get()->getResultArray();

        $instancias = array_map(self::aInstancia(...), $filas);
        $estados    = array_column($instancias, 'estado');

        return [
            'fecha'      => $fecha,
            'es_hoy'     => $fecha === $hoy,
            'instancias' => $instancias,
            'resumen'    => [
                'total'     => count($instancias),
                'hechas'    => count(array_filter($estados, static fn (string $e): bool => $e === 'hecha' || $e === 'aprobada')),
                'aprobadas' => count(array_filter($estados, static fn (string $e): bool => $e === 'aprobada')),
            ],
        ];
    }

    /**
     * E-12 · `pendiente`/`devuelta` → `hecha` (o `aprobada` si es actividad de 0 puntos).
     * No acredita puntos (RN-03). La foto la procesa el S3.
     *
     * @return array<string, mixed>
     */
    public function marcarHecha(int $perfilId, int $instanciaId): array
    {
        $this->db->transBegin();

        try {
            $fila = $this->db->query(
                'SELECT i.id, i.perfil_id, i.fecha, i.estado, i.puntos, t.tipo
                   FROM tarea_instancias i JOIN tareas t ON t.id = i.tarea_id
                  WHERE i.id = ? FOR UPDATE',
                [$instanciaId],
            )->getRowArray();

            if ($fila === null) {
                throw ApiException::noEncontrado();
            }

            if ((int) $fila['perfil_id'] !== $perfilId) {
                throw ApiException::prohibido('Esta tarea es de otra persona.');
            }

            if ($fila['fecha'] > $this->hoy()) {
                throw ApiException::estadoInvalido('Esta tarea es de un día que todavía no llega.');
            }

            if (! in_array($fila['estado'], ['pendiente', 'devuelta'], true)) {
                throw ApiException::estadoInvalido('Esta tarea ya está marcada.');
            }

            $ahora    = Time::now()->toDateTimeString();
            $sinPunto = $fila['tipo'] === 'actividad' && (int) $fila['puntos'] === 0;

            $this->db->table('tarea_instancias')->where('id', $instanciaId)->update([
                'estado'        => $sinPunto ? 'aprobada' : 'hecha',
                'completada_en' => $ahora,
                'aprobada_en'   => $sinPunto ? $ahora : null,
                'updated_at'    => $ahora,
            ]);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return self::aInstancia($this->consulta()->where('i.id', $instanciaId)->get()->getRowArray() ?? []);
    }

    private function consulta(): BaseBuilder
    {
        return $this->db->table('tarea_instancias i')
            ->select('i.*, t.titulo, t.icono, t.tipo, t.materia, t.requiere_foto, c.color, e.id AS evidencia_id')
            ->join('tareas t', 't.id = i.tarea_id')
            ->join('categorias c', 'c.id = t.categoria_id')
            ->join('evidencias e', 'e.instancia_id = i.id', 'left');
    }

    /**
     * Forma `Instancia` del contrato. Título, ícono, tipo, materia y `requiere_foto` vienen de la tarea;
     * puntos, hora y entrega, de la instancia; el color, de la categoría.
     *
     * @param array<string, mixed> $fila
     *
     * @return array<string, mixed>
     */
    public static function aInstancia(array $fila): array
    {
        $evidencia = $fila['evidencia_id'] === null ? null : (int) $fila['evidencia_id'];

        return [
            'id'                 => (int) $fila['id'],
            'tarea_id'           => (int) $fila['tarea_id'],
            'perfil_id'          => (int) $fila['perfil_id'],
            'fecha'              => $fila['fecha'],
            'hora'               => $fila['hora'] === null ? null : substr((string) $fila['hora'], 0, 5),
            'fecha_entrega'      => $fila['fecha_entrega'],
            'titulo'             => $fila['titulo'],
            'icono'              => $fila['icono'],
            'color'              => $fila['color'],
            'tipo'               => $fila['tipo'],
            'materia'            => $fila['materia'],
            'puntos'             => (int) $fila['puntos'],
            'estado'             => $fila['estado'],
            'requiere_foto'      => (bool) (int) $fila['requiere_foto'],
            'tiene_evidencia'    => $evidencia !== null,
            'evidencia_id'       => $evidencia,
            'mensaje_devolucion' => $fila['mensaje_devolucion'],
            'completada_en'      => Fechas::iso($fila['completada_en']),
        ];
    }
}
