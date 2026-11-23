<?php

declare(strict_types=1);

namespace App\Filters;

use App\Exceptions\ApiException;
use App\Models\UsuarioModel;
use App\Services\SesionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * `sesion` (doc 04 §3.2): exige un `usuario_id` vigente en la sesión del dispositivo.
 */
final class SesionFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $sesion = new SesionService();
        $id     = $sesion->usuarioId();

        if ($id === null || model(UsuarioModel::class)->find($id) === null) {
            if ($id !== null) {
                $sesion->cerrar();
            }

            return ApiException::sinSesion()->getResponse();
        }

        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): null
    {
        return null;
    }
}
