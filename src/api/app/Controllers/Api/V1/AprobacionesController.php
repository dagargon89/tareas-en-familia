<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\AprobacionService;
use App\Services\SesionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Bandeja y revisión en modo padre: E-33, E-34 y E-35.
 */
final class AprobacionesController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok((new AprobacionService(db_connect()))->bandeja());
    }

    public function revisar(string $id): ResponseInterface
    {
        return $this->ok((new AprobacionService(db_connect()))->revisar((int) $id, $this->entrada(), $this->usuarioId()));
    }

    public function lote(): ResponseInterface
    {
        return $this->ok((new AprobacionService(db_connect()))->aprobarLote($this->entrada(), $this->usuarioId()));
    }

    private function usuarioId(): int
    {
        return (int) (new SesionService())->usuarioId();
    }
}
