<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\AsistenteService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Endpoint del asistente IA acotado a la app (ADR-012): E-56.
 */
final class AsistenteController extends BaseApiController
{
    public function mensaje(): ResponseInterface
    {
        $servicio = new AsistenteService(db_connect());

        return $this->ok($servicio->responder($this->entrada()));
    }
}
