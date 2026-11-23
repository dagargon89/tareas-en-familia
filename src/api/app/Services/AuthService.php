<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Libraries\Hash;
use App\Models\UsuarioModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Throttle\ThrottlerInterface;

/**
 * Login del padre, logout y modo padre (RF-02, doc 04 §3.1).
 */
final class AuthService
{
    public const INTENTOS_LOGIN_POR_MINUTO = 5;

    public function __construct(
        private readonly SesionService $sesion = new SesionService(),
        private readonly ?UsuarioModel $usuarios = null,
        private readonly ?ThrottlerInterface $throttler = null,
    ) {
    }

    private function usuarios(): UsuarioModel
    {
        return $this->usuarios ?? model(UsuarioModel::class);
    }

    /**
     * @param array<string, mixed> $entrada `{ email, password }`
     *
     * @throws ApiException 429, 400 `VALIDACION` o 401 `CREDENCIALES`
     */
    public function login(array $entrada, string $ip): void
    {
        $throttler = $this->throttler ?? service('throttler');

        if (! $throttler->check('login_' . md5($ip), self::INTENTOS_LOGIN_POR_MINUTO, MINUTE)) {
            throw ApiException::demasiadosIntentos($throttler->getTokenTime());
        }

        $email    = $entrada['email'] ?? null;
        $password = $entrada['password'] ?? null;
        $campos   = [];

        if (! is_string($email) || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            $campos['email'] = 'Escribe un correo válido.';
        }

        if (! is_string($password) || $password === '') {
            $campos['password'] = 'Escribe tu contraseña.';
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        /** @var string $email */
        /** @var string $password */
        $usuario = $this->usuarios()->porEmail($email);

        // Con correo inexistente se verifica igual contra un hash fijo para no revelar qué correos existen.
        $hash = $usuario['password_hash'] ?? '$2y$12$y0xqPc1EefwH4KLmE9KVR.mKi9uUY/GRTj2pOjixSpR9KqLbHC9KK';

        if (! Hash::verificar($password, $hash) || $usuario === null) {
            throw ApiException::credenciales();
        }

        $cambios = ['ultimo_acceso' => Time::now()->toDateTimeString()];

        if (Hash::necesitaRehash($usuario['password_hash'])) {
            $cambios['password_hash'] = Hash::crear($password);
        }

        $this->usuarios()->update((int) $usuario['id'], $cambios);
        $this->sesion->iniciar((int) $usuario['id']);
    }

    public function logout(): void
    {
        $this->sesion->cerrar();
    }

    /**
     * Modo padre con el PIN del usuario dueño de la sesión (RF-02).
     *
     * @param array<string, mixed> $entrada `{ pin }`
     */
    public function entrarModoPadre(array $entrada, PinService $pines): void
    {
        $pin = $entrada['pin'] ?? null;

        if (! PinService::formatoValido($pin)) {
            throw ApiException::validacion(['pin' => 'El PIN debe tener 4 dígitos.']);
        }

        $usuarioId = $this->sesion->usuarioId() ?? throw ApiException::sinSesion();

        $pines->verificar('usuarios', $usuarioId, $pin);
        $this->sesion->activarModoPadre();
    }
}
