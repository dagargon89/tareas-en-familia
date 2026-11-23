<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\CanjesService;
use App\Services\SesionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Canjes en modo padre: E-40 y E-41.
 */
final class CanjesController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok((new CanjesService(db_connect()))->admin($this->request->getGet('estado')));
    }

    public function revisar(string $id): ResponseInterface
    {
        $usuarioId = (int) (new SesionService())->usuarioId();

        return $this->ok((new CanjesService(db_connect()))->revisar((int) $id, $this->entrada(), $usuarioId));
    }
}
