<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Libraries\Hash;
use App\Libraries\LimiteIntentos;
use App\Models\UsuarioModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Instalacion;
use RuntimeException;

/**
 * Instalación inicial (RF-20): alta del primer padre solo con 0 usuarios.
 */
final class InstalacionService
{
    private const CANDADO = 'tareas_instalacion';

    private readonly Instalacion $config;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly SesionService $sesion = new SesionService(),
        ?Instalacion $config = null,
    ) {
        $this->config = $config ?? config(Instalacion::class);
    }

    /**
     * E-49 · `EstadoInstalacion`.
     *
     * @return array{requerida: bool, requiere_token: bool}
     */
    public function estado(): array
    {
        return [
            'requerida'      => $this->db->table('usuarios')->countAllResults() === 0,
            'requiere_token' => $this->config->tokenRequerido(),
        ];
    }

    /**
     * E-50 · Crea el primer usuario y abre la sesión del dispositivo.
     *
     * @param array<string, mixed> $entrada `InstalacionEntrada`
     *
     * @throws ApiException 429, 409 `ESTADO_INVALIDO`, 403 `PROHIBIDO` o 400 `VALIDACION`
     */
    public function instalar(array $entrada, string $ip): void
    {
        LimiteIntentos::verificar($ip);

        if ($this->db->table('usuarios')->countAllResults() > 0) {
            throw self::cerrada();
        }

        if ($this->config->tokenRequerido()) {
            $token = $entrada['token'] ?? null;

            if ($this->config->token === '' || ! is_string($token) || ! hash_equals($this->config->token, $token)) {
                throw ApiException::prohibido('El token de instalación no es correcto.');
            }
        }

        $datos = $this->validar($entrada);
        $id    = $this->crearEnExclusiva($datos);

        $this->sesion->iniciar($id);
    }

    private static function cerrada(): ApiException
    {
        return ApiException::estadoInvalido('La app ya está configurada. Inicia sesión.');
    }

    /**
     * Comprueba y crea bajo bloqueo para que dos instalaciones simultáneas no creen dos usuarios:
     * candado con nombre (serializa) + `SELECT … FOR UPDATE` dentro de la transacción (doc 05 §2.1).
     *
     * @param array<string, string> $datos
     */
    private function crearEnExclusiva(array $datos): int
    {
        $candado = $this->db->query('SELECT GET_LOCK(?, 10) AS ok', [self::CANDADO])->getRowArray();

        if ((int) ($candado['ok'] ?? 0) !== 1) {
            throw new RuntimeException('No se obtuvo el candado de instalación.');
        }

        try {
            $this->db->transBegin();

            try {
                $existentes = $this->db->query('SELECT id FROM usuarios FOR UPDATE')->getResultArray();

                if ($existentes !== []) {
                    $this->db->transRollback();

                    throw self::cerrada();
                }

                $ahora = Time::now()->toDateTimeString();
                $this->db->table('usuarios')->insert($datos + ['created_at' => $ahora, 'updated_at' => $ahora]);
                $id = (int) $this->db->insertID();
                $this->db->transCommit();
            } catch (ApiException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->db->transRollback();

                throw $e;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [self::CANDADO]);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return array<string, string>
     */
    private function validar(array $entrada): array
    {
        $campos   = [];
        $nombre   = is_string($entrada['nombre'] ?? null) ? trim($entrada['nombre']) : '';
        $email    = is_string($entrada['email'] ?? null) ? UsuarioModel::normalizarEmail($entrada['email']) : '';
        $password = $entrada['password'] ?? null;

        if ($nombre === '' || mb_strlen($nombre) > 60) {
            $campos['nombre'] = 'Escribe tu nombre (máximo 60 caracteres).';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 120) {
            $campos['email'] = 'Escribe un correo válido.';
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
}
