<?php

declare(strict_types=1);

namespace App\Filters;

use App\Exceptions\ApiException;
use App\Models\PerfilModel;
use App\Services\SesionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * `perfilActivo` (doc 04 §3.2): rutas `/yo/*` con un perfil activo en la sesión.
 */
final class PerfilActivoFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $sesion = new SesionService();
        $id     = $sesion->perfilActivoId();

        if ($id === null || model(PerfilModel::class)->activo($id) === null) {
            $sesion->salirPerfil();

            return ApiException::sinPerfil()->getResponse();
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
