<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\SesionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * E-36 · `GET /evidencias/{id}`: la foto WebP, solo para la dueña o el padre en modo padre (doc 04 §3.5).
 */
final class EvidenciasController extends BaseApiController
{
    public function ver(string $id): ResponseInterface
    {
        $sesion  = new SesionService();
        $archivo = service('evidencias')->archivoPara((int) $id, $sesion->perfilActivoId(), $sesion->modoPadreHasta() !== null);

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'image/webp')
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setBody((string) file_get_contents($archivo));
    }
}
