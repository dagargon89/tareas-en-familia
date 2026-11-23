<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ReglaRecurrencia;
use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Meta familiar (RF-12): E-42 a E-44, cumplimiento al aprobar y vencimiento por cron.
 */
final class MetasService
{
    public const ESTADOS = ['activa', 'cumplida', 'cancelada'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * E-42 · Todas las metas, la activa primero y luego las más recientes.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        $filas = $this->db->table('metas_familiares')
            ->orderBy("estado = 'activa'", 'DESC', false)
            ->orderBy('fecha_inicio', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()->getResultArray();

        return array_map($this->aMeta(...), $filas);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activa(): ?array
    {
        $fila = $this->db->table('metas_familiares')->where('estado', 'activa')->get()->getRowArray();

        return $fila === null ? null : $this->aMeta($fila);
    }

    /**
     * E-43 · Solo una activa a la vez (409 `ESTADO_INVALIDO`).
     *
     * @param array<string, mixed> $entrada `MetaEntrada`
     *
     * @return array<string, mixed>
     */
    public function crear(array $entrada): array
    {
        $datos = $this->validar($entrada);

        if ($this->db->table('metas_familiares')->where('estado', 'activa')->countAllResults() > 0) {
            throw ApiException::estadoInvalido('Ya hay una meta activa. Termínala o cancélala antes de crear otra.');
        }

        $ahora = Time::now()->toDateTimeString();

        try {
            $this->db->table('metas_familiares')->insert($datos + ['estado' => 'activa', 'created_at' => $ahora, 'updated_at' => $ahora]);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            if (str_contains($e->getMessage(), 'uq_metas_activa_unica')) {
                throw ApiException::estadoInvalido('Ya hay una meta activa.');
            }

            throw $e;
        }

        return $this->buscar((int) $this->db->insertID());
    }

    /**
     * E-44 · Editar la meta activa y, opcionalmente, pasarla a `cancelada`.
     * Una meta cumplida o cancelada ya no cambia.
     *
     * @param array<string, mixed> $entrada `MetaEntrada & { estado? }`
     *
     * @return array<string, mixed>
     */
    public function editar(int $id, array $entrada): array
    {
        $actual = $this->db->table('metas_familiares')->where('id', $id)->get()->getRowArray() ?? throw ApiException::noEncontrado();
        $estado = $entrada['estado'] ?? $actual['estado'];

        if (! in_array($estado, self::ESTADOS, true)) {
            throw ApiException::validacion(['estado' => 'Estado de meta desconocido.']);
        }

        $datos = $this->validar($entrada);

        if ($actual['estado'] !== 'activa' || ! in_array($estado, ['activa', 'cancelada'], true)) {
            throw ApiException::estadoInvalido('Solo la meta activa se puede editar o cancelar.');
        }

        $this->db->table('metas_familiares')->where('id', $id)->update($datos + [
            'estado' => $estado, 'updated_at' => Time::now()->toDateTimeString(),
        ]);

        return $this->buscar($id);
    }

    /**
     * Dentro de la transacción de aprobar: si la meta activa alcanzó el objetivo, pasa a `cumplida`.
     */
    public function evaluarCumplimiento(): bool
    {
        $meta = $this->db->query("SELECT * FROM metas_familiares WHERE estado = 'activa' FOR UPDATE")->getRowArray();

        if ($meta === null || $this->progreso($meta) < (int) $meta['objetivo_puntos']) {
            return false;
        }

        $ahora = Time::now()->toDateTimeString();
        $this->db->table('metas_familiares')->where('id', $meta['id'])->update(['estado' => 'cumplida', 'cumplida_en' => $ahora, 'updated_at' => $ahora]);

        return true;
    }

    /**
     * Subtarea "vencer meta": cancela la activa con `fecha_limite` ya pasada sin cumplir.
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
     * @return array<string, mixed>
     */
    private function buscar(int $id): array
    {
        return $this->aMeta($this->db->table('metas_familiares')->where('id', $id)->get()->getRowArray() ?? throw ApiException::noEncontrado());
    }

    /**
     * Puntos de origen `tarea` de todas las hijas desde las 00:00 de `fecha_inicio` (doc 03 §4).
     * En una meta cerrada se cuenta hasta que se cumplió o se canceló.
     *
     * @param array<string, mixed> $meta
     */
    private function progreso(array $meta): int
    {
        $q = $this->db->table('movimientos_puntos')
            ->selectSum('puntos', 'total')
            ->where('origen', 'tarea')
            ->where('created_at >=', $meta['fecha_inicio'] . ' 00:00:00');

        if ($meta['estado'] === 'cumplida' && $meta['cumplida_en'] !== null) {
            $q->where('created_at <=', $meta['cumplida_en']);
        } elseif ($meta['estado'] === 'cancelada') {
            $q->where('created_at <=', $meta['updated_at']);
        }

        return (int) ($q->get()->getRowArray()['total'] ?? 0);
    }

    /**
     * Forma `Meta` del contrato.
     *
     * @param array<string, mixed> $fila
     *
     * @return array<string, mixed>
     */
    private function aMeta(array $fila): array
    {
        return [
            'id'              => (int) $fila['id'],
            'titulo'          => $fila['titulo'],
            'premio'          => $fila['premio'],
            'objetivo_puntos' => (int) $fila['objetivo_puntos'],
            'progreso_puntos' => $this->progreso($fila),
            'fecha_inicio'    => $fila['fecha_inicio'],
            'fecha_limite'    => $fila['fecha_limite'],
            'estado'          => $fila['estado'],
        ];
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return array<string, int|string|null>
     */
    private function validar(array $entrada): array
    {
        $campos   = [];
        $titulo   = is_string($entrada['titulo'] ?? null) ? trim($entrada['titulo']) : '';
        $premio   = is_string($entrada['premio'] ?? null) ? trim($entrada['premio']) : '';
        $objetivo = $entrada['objetivo_puntos'] ?? null;
        $inicio   = $entrada['fecha_inicio'] ?? null;
        $limite   = $entrada['fecha_limite'] ?? null;

        if ($titulo === '' || mb_strlen($titulo) > 60) {
            $campos['titulo'] = 'Escribe la meta (máximo 60 caracteres).';
        }

        if ($premio === '' || mb_strlen($premio) > 80) {
            $campos['premio'] = 'Escribe el premio (máximo 80 caracteres).';
        }

        if (! is_int($objetivo) || $objetivo < 1 || $objetivo > 1_000_000) {
            $campos['objetivo_puntos'] = 'Al menos 1 punto.';
        }

        if (! is_string($inicio) || ! ReglaRecurrencia::esFechaValida($inicio)) {
            $campos['fecha_inicio'] = 'Elige el inicio.';
        } elseif ($limite !== null && (! is_string($limite) || ! ReglaRecurrencia::esFechaValida($limite) || $limite < $inicio)) {
            $campos['fecha_limite'] = 'No puede ser antes del inicio.';
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        return ['titulo' => $titulo, 'premio' => $premio, 'objetivo_puntos' => $objetivo, 'fecha_inicio' => $inicio, 'fecha_limite' => $limite];
    }
}
