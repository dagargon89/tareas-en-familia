<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\ConfiguracionService;
use App\Services\SesionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Configuración familiar en modo padre: E-47 y E-48.
 */
final class ConfiguracionController extends BaseApiController
{
    public function ver(): ResponseInterface
    {
        return $this->ok((new ConfiguracionService(db_connect()))->ver());
    }

    public function guardar(): ResponseInterface
    {
        $usuarioId = (int) (new SesionService())->usuarioId();

        return $this->ok((new ConfiguracionService(db_connect()))->guardar($this->entrada(), $usuarioId));
    }
}
