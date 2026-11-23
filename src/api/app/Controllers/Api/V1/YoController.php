<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Entities\Perfil;
use App\Exceptions\ApiException;
use App\Models\PerfilModel;
use App\Services\InstanciasService;
use App\Services\ProgresoService;
use App\Services\SesionService;
use App\Services\TareasService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Rutas de la hija con perfil activo (`/yo/*`): E-11, E-12, E-13 y E-18 a E-21.
 */
final class YoController extends BaseApiController
{
    private function perfil(): Perfil
    {
        $id = (new SesionService())->perfilActivoId();

        return ($id === null ? null : model(PerfilModel::class)->activo($id)) ?? throw ApiException::sinPerfil();
    }

    public function dia(): ResponseInterface
    {
        return $this->ok((new InstanciasService(db_connect()))->dia($this->perfil()->id, $this->request->getGet('fecha')));
    }

    /**
     * `multipart/form-data` con `foto` opcional (obligatoria si la tarea la pide, RF-06).
     */
    public function marcarHecha(string $id): ResponseInterface
    {
        $foto = $this->request->getFile('foto');

        return $this->ok((new InstanciasService(db_connect()))->marcarHecha($this->perfil()->id, (int) $id, $foto));
    }

    public function progreso(): ResponseInterface
    {
        return $this->ok((new ProgresoService(db_connect()))->progreso($this->perfil()->id));
    }

    public function tareas(): ResponseInterface
    {
        return $this->ok((new TareasService(db_connect()))->propias($this->perfil()));
    }

    public function crearTarea(): ResponseInterface
    {
        return $this->creado((new TareasService(db_connect()))->crearPropia($this->perfil(), $this->entrada()));
    }

    public function editarTarea(string $id): ResponseInterface
    {
        return $this->ok((new TareasService(db_connect()))->editarPropia($this->perfil(), (int) $id, $this->entrada()));
    }

    public function desactivarTarea(string $id): ResponseInterface
    {
        (new TareasService(db_connect()))->desactivarPropia($this->perfil(), (int) $id);

        return $this->sinContenido();
    }
}
