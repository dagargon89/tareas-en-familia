<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\CanjesService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Catálogo de recompensas en modo padre: E-37 a E-39.
 */
final class RecompensasController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok((new CanjesService(db_connect()))->recompensas());
    }

    public function crear(): ResponseInterface
    {
        return $this->creado((new CanjesService(db_connect()))->crearRecompensa($this->entrada()));
    }

    public function editar(string $id): ResponseInterface
    {
        return $this->ok((new CanjesService(db_connect()))->editarRecompensa((int) $id, $this->entrada()));
    }
}
