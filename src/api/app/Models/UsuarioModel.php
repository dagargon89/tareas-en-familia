<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * `usuarios`: padre o madre (máximo 2, doc 03 §3).
 */
class UsuarioModel extends Model
{
    public const MAX_USUARIOS = 2;

    protected $table         = 'usuarios';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'nombre', 'email', 'password_hash', 'pin_hash', 'pin_intentos', 'bloqueado_hasta', 'ultimo_acceso',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function porEmail(string $email): ?array
    {
        return $this->where('email', self::normalizarEmail($email))->first();
    }

    public static function normalizarEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
