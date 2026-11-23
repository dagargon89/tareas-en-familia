<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Libraries\ErrorApi;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Base de los controladores de `/api/v1`. Solo traduce HTTP ↔ Services;
 * sin lógica de negocio (doc 02 §3.1).
 */
abstract class BaseApiController extends Controller
{
    /**
     * @param array<array-key, mixed>|object $datos
     */
    protected function ok(array|object $datos, int $estado = 200): ResponseInterface
    {
        return $this->response->setStatusCode($estado)->setJSON($datos);
    }

    /**
     * @param array<array-key, mixed>|object $datos
     */
    protected function creado(array|object $datos): ResponseInterface
    {
        return $this->ok($datos, 201);
    }

    protected function sinContenido(): ResponseInterface
    {
        return $this->response->setStatusCode(204)->setBody('');
    }

    /**
     * Cuerpo JSON de la petición como arreglo (vacío si no es JSON válido).
     *
     * @return array<string, mixed>
     */
    protected function entrada(): array
    {
        if (! str_contains($this->request->getHeaderLine('Content-Type'), 'application/json')) {
            $post = $this->request->getPost();

            return is_array($post) ? $post : [];
        }

        try {
            $datos = $this->request->getJSON(true);
        } catch (\Throwable) {
            $datos = null;
        }

        return is_array($datos) ? $datos : [];
    }

    /**
     * @param array<string, string>|null $campos
     * @param array<string, mixed>       $extra
     */
    protected function error(int $estado, string $codigo, string $mensaje, ?array $campos = null, array $extra = []): ResponseInterface
    {
        return ErrorApi::respuesta($estado, $codigo, $mensaje, $campos, $extra, $this->response);
    }
}
