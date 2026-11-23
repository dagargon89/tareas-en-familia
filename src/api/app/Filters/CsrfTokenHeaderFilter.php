<?php

declare(strict_types=1);

namespace App\Filters;

use App\Controllers\Errores;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Devuelve el token CSRF vigente en el header `X-CSRF-TOKEN` de cada
 * respuesta de la API (doc 05 §1: el token se renueva con cada respuesta).
 *
 * Es filtro requerido (corre también en respuestas de error y de filtros que
 * cortan la petición); solo actúa en `/api/*` y nunca en `/api/v1/salud`, que no abre sesión.
 */
final class CsrfTokenHeaderFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): null
    {
        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        $ruta = ltrim($request instanceof \CodeIgniter\HTTP\IncomingRequest ? $request->getPath() : '', '/');

        if (! Errores::esRutaApi($ruta) || $ruta === 'api/v1/salud') {
            return $response;
        }

        return $response->setHeader('X-CSRF-TOKEN', csrf_hash());
    }
}
