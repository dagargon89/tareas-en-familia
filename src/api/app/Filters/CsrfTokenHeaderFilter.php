<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Devuelve el token CSRF vigente en el header `X-CSRF-TOKEN` de cada
 * respuesta de la API (doc 05 §1: el token se renueva con cada respuesta).
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
        return $response->setHeader('X-CSRF-TOKEN', csrf_hash());
    }
}
