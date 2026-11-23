<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\ErrorApi;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 404 global (`set404Override`). En `/api/*` responde el error uniforme
 * `NO_ENCONTRADO` en JSON; fuera de la API muestra la vista HTML de CI4.
 */
final class Errores extends Controller
{
    public function noEncontrado(): ResponseInterface
    {
        if (self::esRutaApi($this->request->getUri()->getPath())) {
            return ErrorApi::respuesta(404, 'NO_ENCONTRADO', 'No encontramos lo que buscas.', null, [], $this->response);
        }

        return $this->response
            ->setStatusCode(404)
            ->setBody(view('errors/html/error_404', ['message' => 'Página no encontrada.']));
    }

    public static function esRutaApi(string $ruta): bool
    {
        $ruta = ltrim($ruta, '/');

        return $ruta === 'api' || str_starts_with($ruta, 'api/');
    }
}
