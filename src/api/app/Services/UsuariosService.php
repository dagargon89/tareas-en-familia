<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Libraries\Hash;
use App\Models\UsuarioModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\I18n\Time;

/**
 * Padres de la familia (máximo 2, doc 03): E-51 y E-52 (ADR-009) y `php spark usuarios:crear`.
 * Las reglas de alta viven solo aquí; la instalación inicial (RF-20) conserva su propio flujo con bloqueo.
 */
final class UsuariosService
{
    private const DUPLICADO = 1062;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * E-51 · Padres sin datos sensibles; `es_actual` marca al de la sesión.
     *
     * @return list<array{id: int, nombre: string, email: string, es_actual: bool}>
     */
    public function listar(?int $actualId): array
    {
        $filas = $this->db->table('usuarios')->select('id, nombre, email')->orderBy('id')->get()->getResultArray();

        return array_map(static fn (array $f): array => self::aUsuario($f, $actualId), $filas);
    }

    public function hayLugar(): bool
    {
        return $this->db->table('usuarios')->countAllResults() < UsuarioModel::MAX_USUARIOS;
    }

    /**
     * E-52 · Alta de un padre. El máximo se comprueba con las filas bloqueadas (`FOR UPDATE`) para que
     * dos altas simultáneas no pasen de 2.
     *
     * @param array<string, mixed> $entrada `UsuarioEntrada`
     *
     * @return array{id: int, nombre: string, email: string, es_actual: bool}
     */
    public function crear(array $entrada, ?int $actualId = null): array
    {
        $this->db->transBegin();

        try {
            $total = count($this->db->query('SELECT id FROM usuarios FOR UPDATE')->getResultArray());

            if ($total >= UsuarioModel::MAX_USUARIOS) {
                throw ApiException::estadoInvalido('Ya hay ' . UsuarioModel::MAX_USUARIOS . ' padres: el máximo 2 está completo.');
            }

            $datos = $this->validar($entrada);
            $ahora = Time::now()->toDateTimeString();

            try {
                $this->db->table('usuarios')->insert($datos + ['created_at' => $ahora, 'updated_at' => $ahora]);
            } catch (DatabaseException $e) {
                throw $e->getCode() === self::DUPLICADO ? self::correoRepetido() : $e;
            }

            $id = (int) $this->db->insertID();
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return self::aUsuario(['id' => $id, 'nombre' => $datos['nombre'], 'email' => $datos['email']], $actualId);
    }

    /**
     * Reglas de `UsuarioEntrada` (iguales a la instalación, RF-20) y columnas a guardar.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array{nombre: string, email: string, password_hash: string, pin_hash: string}
     */
    private function validar(array $entrada): array
    {
        $campos   = [];
        $nombre   = is_string($entrada['nombre'] ?? null) ? trim($entrada['nombre']) : '';
        $email    = is_string($entrada['email'] ?? null) ? UsuarioModel::normalizarEmail($entrada['email']) : '';
        $password = $entrada['password'] ?? null;

        if ($nombre === '' || mb_strlen($nombre) > 60) {
            $campos['nombre'] = 'Escribe el nombre (máximo 60 caracteres).';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 120) {
            $campos['email'] = 'Escribe un correo válido.';
        } elseif ($this->db->table('usuarios')->where('email', $email)->countAllResults() > 0) {
            $campos['email'] = self::correoRepetido()->getCampos()['email'];
        }

        if (! is_string($password) || mb_strlen($password) < 10) {
            $campos['password'] = 'La contraseña debe tener al menos 10 caracteres.';
        }

        $errorPin = PinService::errorDePinNuevo($entrada['pin'] ?? null);

        if ($errorPin !== null) {
            $campos['pin'] = $errorPin;
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        /** @var string $password */
        return [
            'nombre'        => $nombre,
            'email'         => $email,
            'password_hash' => Hash::crear($password),
            'pin_hash'      => Hash::crear((string) $entrada['pin']),
        ];
    }

    private static function correoRepetido(): ApiException
    {
        return ApiException::validacion(['email' => 'Ese correo ya existe en la familia.']);
    }

    /**
     * @param array<string, mixed> $fila
     *
     * @return array{id: int, nombre: string, email: string, es_actual: bool}
     */
    private static function aUsuario(array $fila, ?int $actualId): array
    {
        return [
            'id'        => (int) $fila['id'],
            'nombre'    => (string) $fila['nombre'],
            'email'     => (string) $fila['email'],
            'es_actual' => $actualId !== null && (int) $fila['id'] === $actualId,
        ];
    }
}
