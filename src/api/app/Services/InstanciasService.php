<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Calendario;
use App\Domain\ReglaRecurrencia;
use App\Domain\SemanaIso;
use App\Exceptions\ApiException;
use App\Libraries\Fechas;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\I18n\Time;

/**
 * "Mi día" (RF-04, RF-13: E-11) y marcar hecha con foto opcional u obligatoria (RF-05, RF-06: E-12).
 */
final class InstanciasService
{
    /**
     * Días hacia atrás y hacia adelante que se pueden consultar (RF-04).
     */
    public const RANGO_DIAS = 7;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly ?EvidenciasService $evidencias = null,
    ) {
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

        return $this->aDia($fecha, $hoy, array_map(self::aInstancia(...), $filas));
    }

    /**
     * E-17 y E-46 · `Semana` ISO (lunes a domingo) que contiene `desde`. Cada instancia aparece solo en
     * su `fecha` (una escolar no se repite hasta su entrega; la entrega va en `fecha_entrega`).
     * Con `$perfilId` null trae las de todas las hijas activas (modo padre).
     *
     * @return array{desde: string, hasta: string, dias: list<array<string, mixed>>}
     */
    public function semana(?int $perfilId, mixed $desde): array
    {
        $hoy   = $this->hoy();
        $desde = $desde === null || $desde === '' ? $hoy : $desde;

        if (! is_string($desde) || ! ReglaRecurrencia::esFechaValida($desde)) {
            throw ApiException::validacion(['desde' => 'Elige una fecha válida.']);
        }

        $semana = SemanaIso::desdeFecha($desde);

        // Como en "Mi día": cubre un cron retrasado solo dentro de una semana antes y después de hoy.
        $inicio = max($semana->lunes(), Calendario::sumarDias($hoy, -self::RANGO_DIAS));
        $fin    = min($semana->domingo(), Calendario::sumarDias($hoy, self::RANGO_DIAS));

        if ($inicio <= $fin) {
            (new GeneradorInstancias($this->db))->generar($inicio, $fin);
        }

        $consulta = $this->consulta()
            ->join('perfiles p', 'p.id = i.perfil_id')
            ->where('i.fecha >=', $semana->lunes())
            ->where('i.fecha <=', $semana->domingo())
            ->orderBy('i.fecha')
            ->orderBy('i.hora IS NULL', '', false)
            ->orderBy('i.hora')
            ->orderBy('p.orden')
            ->orderBy('i.id');

        if ($perfilId === null) {
            $consulta->where('p.activo', 1);
        } else {
            $consulta->where('i.perfil_id', $perfilId);
        }

        $porFecha = array_fill_keys($semana->dias(), []);

        foreach ($consulta->get()->getResultArray() as $fila) {
            $porFecha[$fila['fecha']][] = self::aInstancia($fila);
        }

        $dias = [];

        foreach ($porFecha as $fecha => $instancias) {
            $dias[] = $this->aDia($fecha, $hoy, $instancias);
        }

        return ['desde' => $semana->lunes(), 'hasta' => $semana->domingo(), 'dias' => $dias];
    }

    /**
     * E-46 · `perfil_id` opcional del query string: una hija (activa o no) o todas las activas.
     *
     * @return array{desde: string, hasta: string, dias: list<array<string, mixed>>}
     */
    public function semanaAdmin(mixed $perfilId, mixed $desde): array
    {
        if ($perfilId === null || $perfilId === '') {
            return $this->semana(null, $desde);
        }

        if (! is_string($perfilId) || ! ctype_digit($perfilId)) {
            throw ApiException::validacion(['perfil_id' => 'Elige una hija.']);
        }

        if ($this->db->table('perfiles')->where('id', (int) $perfilId)->countAllResults() === 0) {
            throw ApiException::noEncontrado();
        }

        return $this->semana((int) $perfilId, $desde);
    }

    /**
     * Forma `Dia` del contrato con su resumen.
     *
     * @param list<array<string, mixed>> $instancias
     *
     * @return array{fecha: string, es_hoy: bool, instancias: list<array<string, mixed>>, resumen: array{total: int, hechas: int, aprobadas: int}}
     */
    private function aDia(string $fecha, string $hoy, array $instancias): array
    {
        $estados = array_column($instancias, 'estado');

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
     * No acredita puntos (RN-03). Si la tarea pide foto, es obligatoria; si llega una foto,
     * se valida, se re-codifica y reemplaza a la anterior (RF-06).
     *
     * @return array<string, mixed>
     */
    public function marcarHecha(int $perfilId, int $instanciaId, ?UploadedFile $foto = null): array
    {
        $fila = $this->db->query(
            'SELECT i.id, i.perfil_id, i.fecha, i.estado, i.puntos, t.tipo, t.requiere_foto
               FROM tarea_instancias i JOIN tareas t ON t.id = i.tarea_id WHERE i.id = ?',
            [$instanciaId],
        )->getRowArray();

        $this->validarMarcable($fila, $perfilId);

        /** @var array<string, mixed> $fila */
        $hayFoto = $foto !== null && $foto->getError() !== UPLOAD_ERR_NO_FILE;

        if (! $hayFoto && (int) $fila['requiere_foto'] === 1) {
            throw ApiException::validacion(['foto' => 'Esta tarea necesita una foto.']);
        }

        $evidencias = $this->evidencias ?? service('evidencias');
        $nueva      = $hayFoto ? $evidencias->preparar($perfilId, $foto) : null;
        $anterior   = null;

        $this->db->transBegin();

        try {
            $bloqueada = $this->db->query(
                'SELECT i.id, i.perfil_id, i.fecha, i.estado FROM tarea_instancias i WHERE i.id = ? FOR UPDATE',
                [$instanciaId],
            )->getRowArray();
            $this->validarMarcable($bloqueada, $perfilId);

            $ahora    = Time::now()->toDateTimeString();
            $sinPunto = $fila['tipo'] === 'actividad' && (int) $fila['puntos'] === 0;

            $this->db->table('tarea_instancias')->where('id', $instanciaId)->update([
                'estado'        => $sinPunto ? 'aprobada' : 'hecha',
                'completada_en' => $ahora,
                'aprobada_en'   => $sinPunto ? $ahora : null,
                'updated_at'    => $ahora,
            ]);

            if ($nueva !== null) {
                $anterior = $evidencias->registrar($instanciaId, $nueva);
            }

            // La aprobación automática también cuenta para las insignias (doc 03 §5); 0 puntos no suman a la meta.
            if ($sinPunto) {
                (new InsigniasService($this->db))->evaluar($perfilId);
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            if ($nueva !== null) {
                $evidencias->borrarArchivo($nueva['ruta']);
            }

            throw $e;
        }

        $evidencias->borrarArchivo($anterior);

        return $this->instancia($instanciaId);
    }

    /**
     * @param array<string, mixed>|null $fila
     */
    private function validarMarcable(?array $fila, int $perfilId): void
    {
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
    }

    /**
     * Una instancia con la forma `Instancia`.
     *
     * @return array<string, mixed>
     */
    public function instancia(int $id): array
    {
        return self::aInstancia($this->consulta()->where('i.id', $id)->get()->getRowArray() ?? throw ApiException::noEncontrado());
    }

    /**
     * Consulta base de `Instancia` (tarea, color de la categoría y evidencia).
     */
    public function consulta(): BaseBuilder
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
