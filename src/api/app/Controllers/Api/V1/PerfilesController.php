<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Services\PerfilesService;
use App\Services\SesionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Selector de perfil (E-06 a E-08) y gestión de perfiles en modo padre (E-23 a E-27).
 */
final class PerfilesController extends BaseApiController
{
    private ?SesionService $sesion = null;

    private function servicio(): PerfilesService
    {
        $this->sesion ??= new SesionService();

        return new PerfilesService(db_connect(), $this->sesion);
    }

    public function index(): ResponseInterface
    {
        return $this->ok($this->servicio()->publicos());
    }

    public function entrar(string $id): ResponseInterface
    {
        $this->servicio()->entrar((int) $id, $this->entrada());

        return $this->ok($this->sesion->estado());
    }

    public function salir(): ResponseInterface
    {
        $this->servicio()->salir();

        return $this->ok($this->sesion->estado());
    }

    public function adminIndex(): ResponseInterface
    {
        return $this->ok($this->servicio()->admin());
    }

    public function crear(): ResponseInterface
    {
        return $this->creado($this->servicio()->crear($this->entrada()));
    }

    public function editar(string $id): ResponseInterface
    {
        return $this->ok($this->servicio()->editar((int) $id, $this->entrada()));
    }

    public function desbloquear(string $id): ResponseInterface
    {
        return $this->ok($this->servicio()->desbloquear((int) $id));
    }

    public function borrar(string $id): ResponseInterface
    {
        $definitivo = filter_var($this->request->getGet('definitivo') ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->servicio()->borrar((int) $id, $definitivo);

        return $this->sinContenido();
    }
}
