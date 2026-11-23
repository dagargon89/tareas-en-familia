<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\ErrorApi;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/**
 * CSRF nativo de CI4 (token en sesión, header `X-CSRF-TOKEN`) con la
 * respuesta uniforme `403 { error: { codigo: "CSRF" } }` del doc 05.
 */
final class CsrfFilter extends CSRF
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        try {
            $resultado = parent::before($request, $arguments);
        } catch (SecurityException) {
            return ErrorApi::respuesta(
                403,
                'CSRF',
                'La sesión de seguridad expiró. Vuelve a intentarlo.',
            );
        }

        return $resultado instanceof ResponseInterface ? $resultado : null;
    }
}
