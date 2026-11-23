<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\SesionService;
use App\Services\UsuariosService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Padres de la familia en modo padre: E-51 y E-52 (ADR-009) y E-53 (ADR-010).
 */
final class UsuariosController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok((new UsuariosService(db_connect()))->listar((new SesionService())->usuarioId()));
    }

    public function crear(): ResponseInterface
    {
        return $this->creado((new UsuariosService(db_connect()))->crear($this->entrada(), (new SesionService())->usuarioId()));
    }

    public function cambiarPassword(string $id): ResponseInterface
    {
        return $this->ok((new UsuariosService(db_connect()))->cambiarPassword(
            (int) $id,
            $this->entrada(),
            (int) (new SesionService())->usuarioId(),
            $this->request->getIPAddress(),
        ));
    }
}
