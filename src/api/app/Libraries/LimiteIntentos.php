<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\ApiException;
use CodeIgniter\Throttle\ThrottlerInterface;

/**
 * Throttling de acceso (doc 04 §3.1): 5 intentos por minuto por IP con el
 * `Throttler` de CI4. Login (E-03) e instalación (E-50) comparten el contador.
 */
final class LimiteIntentos
{
    public const POR_MINUTO = 5;

    /**
     * @throws ApiException 429 `DEMASIADOS_INTENTOS`
     */
    public static function verificar(string $ip, ?ThrottlerInterface $throttler = null): void
    {
        $throttler ??= service('throttler');

        if (! $throttler->check('acceso_' . md5($ip), self::POR_MINUTO, MINUTE)) {
            throw ApiException::demasiadosIntentos($throttler->getTokenTime());
        }
    }
}
