<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\MetasService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Meta familiar en modo padre: E-42 a E-44.
 */
final class MetasController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok((new MetasService(db_connect()))->listar());
    }

    public function crear(): ResponseInterface
    {
        return $this->creado((new MetasService(db_connect()))->crear($this->entrada()));
    }

    public function editar(string $id): ResponseInterface
    {
        return $this->ok((new MetasService(db_connect()))->editar((int) $id, $this->entrada()));
    }
}
