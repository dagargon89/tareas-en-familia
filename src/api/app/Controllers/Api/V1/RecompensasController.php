<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\RecompensasService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Catálogo de recompensas en modo padre: E-37 a E-39.
 */
final class RecompensasController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok((new RecompensasService(db_connect()))->listar());
    }

    public function crear(): ResponseInterface
    {
        return $this->creado((new RecompensasService(db_connect()))->crear($this->entrada()));
    }

    public function editar(string $id): ResponseInterface
    {
        return $this->ok((new RecompensasService(db_connect()))->editar((int) $id, $this->entrada()));
    }
}
