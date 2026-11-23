<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Controllers\Errores;
use App\Libraries\ErrorApi;
use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Errores no controlados: en `/api/*` responde el formato uniforme sin traza
 * (la traza ya quedó en `writable/logs`); fuera de la API usa el de CI4.
 */
final class ManejadorExcepciones extends ExceptionHandler
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        if (! $request instanceof IncomingRequest || ! Errores::esRutaApi($request->getUri()->getPath())) {
            parent::handle($exception, $request, $response, $statusCode, $exitCode);

            return;
        }

        $estado = ($statusCode >= 400 && $statusCode < 600) ? $statusCode : 500;

        [$codigo, $mensaje] = match (true) {
            $estado === 404 => ['NO_ENCONTRADO', 'No encontramos lo que buscas.'],
            $estado >= 500  => ['ERROR_INTERNO', 'Algo salió mal. Inténtalo de nuevo en un momento.'],
            default         => ['ERROR', 'No pudimos procesar la solicitud.'],
        };

        ErrorApi::respuesta($estado, $codigo, $mensaje, null, [], $response)->send();

        if (ENVIRONMENT !== 'testing') {
            // @codeCoverageIgnoreStart
            exit($exitCode);
            // @codeCoverageIgnoreEnd
        }
    }
}
