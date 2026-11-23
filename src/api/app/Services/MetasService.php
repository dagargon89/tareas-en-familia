<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ReglaRecurrencia;
use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\I18n\Time;

/**
 * Meta familiar (RF-12): E-42 a E-44, cumplimiento al aprobar y vencimiento por cron.
 *
 * Progreso = puntos de origen `tarea` de todos los perfiles desde `fecha_inicio` (doc 03 §4); los XP
 * de insignias no suman. Solo una meta `activa` a la vez (`activa_unica` con índice único).
 */
final class MetasService
{
    public const MAX_TITULO   = 60;
    public const MAX_PREMIO   = 80;
    public const MAX_OBJETIVO = 1_000_000;
    public const ESTADOS      = ['activa', 'cumplida', 'cancelada'];

    private const DUPLICADO = 1062;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * E-42 · Todas las metas, la más reciente primero.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        $filas = $this->db->table('metas_familiares')->orderBy('id', 'DESC')->get()->getResultArray();

        return array_map($this->aMeta(...), $filas);
    }

    /**
     * Meta `activa` con su progreso, o null (E-13 y E-45).
     *
     * @return array<string, mixed>|null
     */
    public function activa(): ?array
    {
        $fila = $this->db->table('metas_familiares')->where('estado', 'activa')->get()->getRowArray();

        return $fila === null ? null : $this->aMeta($fila);
    }

    /**
     * E-43 · Crear con otra meta activa responde 409 `ESTADO_INVALIDO` (CP-071).
     *
     * @param array<string, mixed> $entrada `MetaEntrada`
     *
     * @return array<string, mixed>
     */
    public function crear(array $entrada): array
    {
        $datos = $this->validar($entrada);

        if ($this->db->table('metas_familiares')->where('estado', 'activa')->countAllResults() > 0) {
            throw self::yaHayActiva();
        }

        $ahora = Time::now()->toDateTimeString();

        try {
            $this->db->table('metas_familiares')->insert($datos + ['estado' => 'activa', 'created_at' => $ahora, 'updated_at' => $ahora]);
        } catch (DatabaseException $e) {
            // Otra petición creó una meta activa entre la comprobación y el insert.
            throw $e->getCode() === self::DUPLICADO ? self::yaHayActiva() : $e;
        }

        return $this->meta((int) $this->db->insertID());
    }

    /**
     * E-44 · Solo se edita una meta `activa`; el padre puede pasarla a `cancelada` (doc 05 §2.4).
     *
     * @param array<string, mixed> $entrada `MetaEntrada & { estado? }`
     *
     * @return array<string, mixed>
     */
    public function editar(int $id, array $entrada): array
    {
        $actual = $this->db->table('metas_familiares')->where('id', $id)->get()->getRowArray() ?? throw ApiException::noEncontrado();
        $estado = $entrada['estado'] ?? 'activa';

        if (! in_array($estado, self::ESTADOS, true)) {
            throw ApiException::validacion(['estado' => 'Estado de meta desconocido.']);
        }

        $datos = $this->validar($entrada);

        if ($actual['estado'] !== 'activa' || ! in_array($estado, ['activa', 'cancelada'], true)) {
            throw ApiException::estadoInvalido('Solo se puede editar o cancelar la meta activa.');
        }

        $this->db->table('metas_familiares')->where('id', $id)->update($datos + [
            'estado'     => $estado,
            'updated_at' => Time::now()->toDateTimeString(),
        ]);

        return $this->meta($id);
    }

    /**
     * Dentro de la transacción de aprobar: si la meta activa llegó al objetivo, pasa a `cumplida`.
     *
     * @return bool Si se cumplió con esta aprobación (`ResultadoRevision.meta_cumplida`)
     */
    public function evaluarCumplimiento(): bool
    {
        $meta = $this->db->query("SELECT * FROM metas_familiares WHERE estado = 'activa' FOR UPDATE")->getRowArray();

        if ($meta === null || $this->progreso($meta) < (int) $meta['objetivo_puntos']) {
            return false;
        }

        $ahora = Time::now()->toDateTimeString();
        $this->db->table('metas_familiares')->where('id', $meta['id'])->update([
            'estado' => 'cumplida', 'cumplida_en' => $ahora, 'updated_at' => $ahora,
        ]);

        return true;
    }

    /**
     * Cron: cancela la meta activa cuya `fecha_limite` ya pasó (la meta vale todo su último día).
     *
     * @return int Metas canceladas
     */
    public function vencer(string $hoy): int
    {
        $this->db->table('metas_familiares')
            ->where('estado', 'activa')
            ->where('fecha_limite <', $hoy)
            ->update(['estado' => 'cancelada', 'updated_at' => Time::now()->toDateTimeString()]);

        return $this->db->affectedRows();
    }

    /**
     * Puntos de tareas aprobadas desde `fecha_inicio`. En una meta terminada el progreso se
     * congela al cumplirse (`cumplida_en`) o al cancelarse (`updated_at`).
     *
     * @param array<string, mixed> $meta
     */
    private function progreso(array $meta): int
    {
        $consulta = $this->db->table('movimientos_puntos')
            ->selectSum('puntos', 'total')
            ->where('origen', 'tarea')
            ->where('created_at >=', $meta['fecha_inicio'] . ' 00:00:00');

        $hasta = match ($meta['estado']) {
            'cumplida'  => $meta['cumplida_en'],
            'cancelada' => $meta['updated_at'],
            default     => null,
        };

        if ($hasta !== null) {
            $consulta->where('created_at <=', $hasta);
        }

        return (int) ($consulta->get()->getRowArray()['total'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(int $id): array
    {
        return $this->aMeta($this->db->table('metas_familiares')->where('id', $id)->get()->getRowArray() ?? throw ApiException::noEncontrado());
    }

    /**
     * Fila → `Meta` del contrato.
     *
     * @param array<string, mixed> $fila
     *
     * @return array<string, mixed>
     */
    private function aMeta(array $fila): array
    {
        return [
            'id'              => (int) $fila['id'],
            'titulo'          => (string) $fila['titulo'],
            'premio'          => (string) $fila['premio'],
            'objetivo_puntos' => (int) $fila['objetivo_puntos'],
            'progreso_puntos' => $this->progreso($fila),
            'fecha_inicio'    => (string) $fila['fecha_inicio'],
            'fecha_limite'    => $fila['fecha_limite'],
            'estado'          => (string) $fila['estado'],
        ];
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return array{titulo: string, premio: string, objetivo_puntos: int, fecha_inicio: string, fecha_limite: string|null}
     */
    private function validar(array $entrada): array
    {
        $campos   = [];
        $titulo   = is_string($entrada['titulo'] ?? null) ? trim($entrada['titulo']) : '';
        $premio   = is_string($entrada['premio'] ?? null) ? trim($entrada['premio']) : '';
        $objetivo = $entrada['objetivo_puntos'] ?? null;
        $inicio   = $entrada['fecha_inicio'] ?? null;
        $limite   = $entrada['fecha_limite'] ?? null;

        if ($titulo === '' || mb_strlen($titulo) > self::MAX_TITULO) {
            $campos['titulo'] = 'Escribe la meta en hasta 60 caracteres.';
        }

        if ($premio === '' || mb_strlen($premio) > self::MAX_PREMIO) {
            $campos['premio'] = 'Escribe el premio en hasta 80 caracteres.';
        }

        if (! is_int($objetivo) || $objetivo < 1 || $objetivo > self::MAX_OBJETIVO) {
            $campos['objetivo_puntos'] = 'El objetivo debe ser de al menos 1 punto.';
        }

        $inicioValido = is_string($inicio) && ReglaRecurrencia::esFechaValida($inicio);

        if (! $inicioValido) {
            $campos['fecha_inicio'] = 'Elige una fecha de inicio válida.';
        }

        if ($limite !== null && (! is_string($limite) || ! ReglaRecurrencia::esFechaValida($limite) || ($inicioValido && $limite < $inicio))) {
            $campos['fecha_limite'] = 'La fecha límite no puede ser antes del inicio.';
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        return ['titulo' => $titulo, 'premio' => $premio, 'objetivo_puntos' => $objetivo, 'fecha_inicio' => $inicio, 'fecha_limite' => $limite];
    }

    private static function yaHayActiva(): ApiException
    {
        return ApiException::estadoInvalido('Ya hay una meta activa. Termínala o cancélala antes de crear otra.');
    }
}
