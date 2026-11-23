<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Proyecto;

/**
 * E-01 · `GET /api/v1/salud`.
 */
final class SaludController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok([
            'estado'  => 'ok',
            'version' => config(Proyecto::class)->version,
        ]);
    }
}
