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
        return $this->ok((new ConfiguracionService(db_connect()))->obtener());
    }

    public function guardar(): ResponseInterface
    {
        return $this->ok((new ConfiguracionService(db_connect()))->guardar($this->entrada(), (int) (new SesionService())->usuarioId()));
    }
}
