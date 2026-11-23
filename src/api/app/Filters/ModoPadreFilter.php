<?php

declare(strict_types=1);

namespace App\Filters;

use App\Exceptions\ApiException;
use App\Services\SesionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * `modoPadre` (doc 04 §3.2): `modo_padre_hasta` mayor a ahora; cada petición
 * válida lo renueva 10 minutos. Si venció, 403 `MODO_PADRE_REQUERIDO`.
 */
final class ModoPadreFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (! (new SesionService())->renovarModoPadre()) {
            return ApiException::modoPadreRequerido()->getResponse();
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
