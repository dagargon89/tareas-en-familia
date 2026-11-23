<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * `configuracion`: clave → valor JSON (doc 03 §6).
 */
class ConfiguracionModel extends Model
{
    protected $table         = 'configuracion';
    protected $primaryKey    = 'clave';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['clave', 'valor', 'updated_at'];

    /**
     * Valor decodificado de la clave, o `$porOmision` si no existe.
     */
    public function valor(string $clave, mixed $porOmision = null): mixed
    {
        $fila = $this->find($clave);

        return $fila === null ? $porOmision : json_decode((string) $fila['valor'], true);
    }

    public function entero(string $clave, int $porOmision): int
    {
        $valor = $this->valor($clave, $porOmision);

        return is_numeric($valor) ? (int) $valor : $porOmision;
    }
}
