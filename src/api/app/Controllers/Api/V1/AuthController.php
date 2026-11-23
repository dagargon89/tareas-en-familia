<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\AuthService;
use App\Services\SesionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * E-03 `POST /auth/login`, E-04 `POST /auth/logout`, E-05 `GET /sesion`.
 * No hay endpoint de registro: el padre se da de alta con `php spark usuarios:crear`.
 */
final class AuthController extends BaseApiController
{
    public function login(): ResponseInterface
    {
        $sesion = new SesionService();
        (new AuthService($sesion))->login($this->entrada(), $this->request->getIPAddress());

        return $this->ok($sesion->estado());
    }

    public function logout(): ResponseInterface
    {
        (new AuthService())->logout();

        return $this->sinContenido();
    }

    public function sesion(): ResponseInterface
    {
        return $this->ok((new SesionService())->estado());
    }
}
