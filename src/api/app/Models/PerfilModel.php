<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Perfil;
use CodeIgniter\Model;

/**
 * `perfiles`: cada hija (sin datos personales fuera del doc 03).
 */
class PerfilModel extends Model
{
    public const COLORES = ['indigo', 'ambar', 'menta', 'rosa', 'lavanda'];
    public const NIVELES = ['exploradoras', 'aventureras', 'pro'];

    protected $table         = 'perfiles';
    protected $primaryKey    = 'id';
    protected $returnType    = Perfil::class;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'apodo', 'avatar', 'color', 'nivel_ux', 'pin_hash', 'pin_intentos', 'bloqueado_hasta',
        'sonidos', 'tema', 'orden', 'activo',
    ];

    /**
     * @return list<Perfil>
     */
    public function activosOrdenados(): array
    {
        return $this->where('activo', 1)->orderBy('orden')->orderBy('id')->findAll();
    }

    /**
     * @return list<Perfil>
     */
    public function todosOrdenados(): array
    {
        return $this->orderBy('orden')->orderBy('id')->findAll();
    }

    public function activo(int $id): ?Perfil
    {
        return $this->where('activo', 1)->find($id);
    }
}
