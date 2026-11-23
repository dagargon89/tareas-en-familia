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
        $estado = $this->request->getGet('estado');

        return $this->ok((new CanjesService(db_connect()))->listar(null, is_string($estado) && $estado !== '' ? $estado : null));
    }

    public function revisar(string $id): ResponseInterface
    {
        return $this->ok((new CanjesService(db_connect()))->revisar((int) $id, $this->entrada(), (int) (new SesionService())->usuarioId()));
    }
}
