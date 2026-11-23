<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * E-02 · `GET /api/v1/csrf`. El cliente envía el token en el header
 * `X-CSRF-TOKEN` en toda petición mutante.
 */
final class CsrfController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok(['token' => csrf_hash()]);
    }
}
