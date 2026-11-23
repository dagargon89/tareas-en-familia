<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\SesionService;
use App\Services\TareasService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Tareas del padre en modo padre: E-28 a E-32.
 */
final class TareasController extends BaseApiController
{
    private function servicio(): TareasService
    {
        return new TareasService(db_connect());
    }

    public function index(): ResponseInterface
    {
        $perfil = $this->request->getGet('perfil_id');
        $activa = $this->request->getGet('activa');

        return $this->ok($this->servicio()->listar(
            is_numeric($perfil) ? (int) $perfil : null,
            is_string($activa) && $activa !== '' ? filter_var($activa, FILTER_VALIDATE_BOOLEAN) : null,
        ));
    }

    public function crear(): ResponseInterface
    {
        return $this->creado($this->servicio()->crear($this->entrada(), (int) (new SesionService())->usuarioId()));
    }

    public function editar(string $id): ResponseInterface
    {
        return $this->ok($this->servicio()->editar((int) $id, $this->entrada()));
    }

    public function desactivar(string $id): ResponseInterface
    {
        $this->servicio()->desactivar((int) $id);

        return $this->sinContenido();
    }

    public function categorias(): ResponseInterface
    {
        return $this->ok($this->servicio()->categorias());
    }
}
