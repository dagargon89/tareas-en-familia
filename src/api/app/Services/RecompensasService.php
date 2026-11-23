<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Catalogo;
use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Catálogo de recompensas del padre (RF-09): E-37 a E-39.
 */
final class RecompensasService
{
    public const COSTO_MAXIMO = 9999;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @return list<array{id: int, titulo: string, icono: string, costo: int, activa: bool}>
     */
    public function listar(bool $soloActivas = false): array
    {
        $q = $this->db->table('recompensas')->orderBy('costo')->orderBy('id');

        if ($soloActivas) {
            $q->where('activa', 1);
        }

        return array_map(self::aRecompensa(...), $q->get()->getResultArray());
    }

    /**
     * @param array<string, mixed> $entrada `RecompensaEntrada`
     *
     * @return array{id: int, titulo: string, icono: string, costo: int, activa: bool}
     */
    public function crear(array $entrada): array
    {
        $ahora = Time::now()->toDateTimeString();
        $this->db->table('recompensas')->insert($this->validar($entrada) + ['created_at' => $ahora, 'updated_at' => $ahora]);

        return $this->buscar((int) $this->db->insertID());
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return array{id: int, titulo: string, icono: string, costo: int, activa: bool}
     */
    public function editar(int $id, array $entrada): array
    {
        $this->buscar($id);
        $this->db->table('recompensas')->where('id', $id)->update($this->validar($entrada) + ['updated_at' => Time::now()->toDateTimeString()]);

        return $this->buscar($id);
    }

    /**
     * @return array{id: int, titulo: string, icono: string, costo: int, activa: bool}
     */
    public function buscar(int $id): array
    {
        $fila = $this->db->table('recompensas')->where('id', $id)->get()->getRowArray();

        return self::aRecompensa($fila ?? throw ApiException::noEncontrado());
    }

    /**
     * @param array<string, mixed> $fila
     *
     * @return array{id: int, titulo: string, icono: string, costo: int, activa: bool}
     */
    public static function aRecompensa(array $fila): array
    {
        return [
            'id'     => (int) $fila['id'],
            'titulo' => $fila['titulo'],
            'icono'  => $fila['icono'],
            'costo'  => (int) $fila['costo'],
            'activa' => (bool) (int) $fila['activa'],
        ];
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return array<string, int|string>
     */
    private function validar(array $entrada): array
    {
        $campos = [];
        $titulo = is_string($entrada['titulo'] ?? null) ? trim($entrada['titulo']) : '';
        $costo  = $entrada['costo'] ?? null;
        $activa = $entrada['activa'] ?? true;

        if ($titulo === '' || mb_strlen($titulo) > 60) {
            $campos['titulo'] = 'Escribe el premio (máximo 60 caracteres).';
        }

        if (! Catalogo::esIcono($entrada['icono'] ?? null)) {
            $campos['icono'] = 'Elige un ícono de la lista.';
        }

        if (! is_int($costo) || $costo < 1 || $costo > self::COSTO_MAXIMO) {
            $campos['costo'] = 'Entre 1 y ' . self::COSTO_MAXIMO . ' puntos.';
        }

        if (! is_bool($activa)) {
            $campos['activa'] = 'Debe ser verdadero o falso.';
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        return ['titulo' => $titulo, 'icono' => (string) $entrada['icono'], 'costo' => $costo, 'activa' => $activa ? 1 : 0];
    }
}
