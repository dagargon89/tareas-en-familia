<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\InstanciasService;
use App\Services\PanelService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Vistas de supervisión en modo padre: E-45 (panel) y E-46 (semana).
 */
final class PanelController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok((new PanelService(db_connect()))->panel($this->request->getGet('semana')));
    }

    public function semana(): ResponseInterface
    {
        return $this->ok((new InstanciasService(db_connect()))->semanaAdmin($this->request->getGet('perfil_id'), $this->request->getGet('desde')));
    }
}
