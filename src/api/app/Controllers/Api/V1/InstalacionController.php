<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\InstalacionService;
use App\Services\SesionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * E-49 `GET /instalacion` y E-50 `POST /instalacion` (RF-20). Públicos; E-50 con CSRF.
 */
final class InstalacionController extends BaseApiController
{
    public function estado(): ResponseInterface
    {
        return $this->ok((new InstalacionService(db_connect()))->estado());
    }

    public function instalar(): ResponseInterface
    {
        $sesion = new SesionService();
        (new InstalacionService(db_connect(), $sesion))->instalar($this->entrada(), $this->request->getIPAddress());

        return $this->creado($sesion->estado());
    }
}
