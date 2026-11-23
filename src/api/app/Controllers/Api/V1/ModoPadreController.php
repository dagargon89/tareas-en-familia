<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\AuthService;
use App\Services\PinService;
use App\Services\SesionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * E-09 `POST /modo-padre` y E-10 `DELETE /modo-padre`.
 */
final class ModoPadreController extends BaseApiController
{
    public function entrar(): ResponseInterface
    {
        $sesion = new SesionService();
        (new AuthService($sesion))->entrarModoPadre($this->entrada(), new PinService(db_connect()));

        return $this->ok($sesion->estado());
    }

    public function salir(): ResponseInterface
    {
        $sesion = new SesionService();
        $sesion->salirModoPadre();

        return $this->ok($sesion->estado());
    }
}
