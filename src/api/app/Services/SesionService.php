<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Fechas;
use App\Models\PerfilModel;
use App\Models\UsuarioModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Session\SessionInterface;
use Config\Session as SessionConfig;

/**
 * Contexto de la sesión del dispositivo (doc 02 §3.3):
 * `usuario_id`, `perfil_activo_id` y `modo_padre_hasta` (timestamp).
 */
final class SesionService
{
    public const MINUTOS_MODO_PADRE = 10;

    private const USUARIO    = 'usuario_id';
    private const PERFIL     = 'perfil_activo_id';
    private const MODO_PADRE = 'modo_padre_hasta';

    private readonly SessionInterface $session;

    public function __construct(?SessionInterface $session = null)
    {
        $this->session = $session ?? session();
    }

    public function usuarioId(): ?int
    {
        $id = $this->session->get(self::USUARIO);

        return $id === null ? null : (int) $id;
    }

    /**
     * Abre la sesión del padre con un id nuevo (doc 04 §3.1).
     */
    public function iniciar(int $usuarioId): void
    {
        $this->regenerar();
        $this->session->remove([self::PERFIL, self::MODO_PADRE]);
        $this->session->set(self::USUARIO, $usuarioId);
    }

    public function cerrar(): void
    {
        $this->session->remove([self::USUARIO, self::PERFIL, self::MODO_PADRE]);
        $this->session->destroy();
    }

    public function perfilActivoId(): ?int
    {
        $id = $this->session->get(self::PERFIL);

        return $id === null ? null : (int) $id;
    }

    public function entrarPerfil(int $perfilId): void
    {
        $this->session->set(self::PERFIL, $perfilId);
    }

    public function salirPerfil(): void
    {
        $this->session->remove(self::PERFIL);
    }

    public function activarModoPadre(): void
    {
        $this->regenerar();
        $this->session->set(self::MODO_PADRE, $this->nuevoVencimiento());
    }

    public function salirModoPadre(): void
    {
        $this->session->remove(self::MODO_PADRE);
    }

    /**
     * Timestamp de vencimiento si el modo padre sigue vigente; si venció, lo cierra.
     */
    public function modoPadreHasta(): ?int
    {
        $hasta = $this->session->get(self::MODO_PADRE);

        if ($hasta === null) {
            return null;
        }

        if ((int) $hasta <= Time::now()->getTimestamp()) {
            $this->salirModoPadre();

            return null;
        }

        return (int) $hasta;
    }

    /**
     * Renueva 10 minutos si el modo padre sigue vigente (cada petición `admin/*`).
     */
    public function renovarModoPadre(): bool
    {
        if ($this->modoPadreHasta() === null) {
            return false;
        }

        $this->session->set(self::MODO_PADRE, $this->nuevoVencimiento());

        return true;
    }

    /**
     * Forma `Sesion` del contrato (doc 05 §3).
     *
     * @return array{usuario: array{id: int, nombre: string}|null, perfil_activo: array<string, mixed>|null, modo_padre_hasta: string|null}
     */
    public function estado(): array
    {
        $usuario = null;
        $id      = $this->usuarioId();

        if ($id !== null) {
            $fila    = model(UsuarioModel::class)->find($id);
            $usuario = $fila === null ? null : ['id' => (int) $fila['id'], 'nombre' => $fila['nombre']];
        }

        $perfil   = null;
        $perfilId = $this->perfilActivoId();

        if ($perfilId !== null) {
            $perfil = model(PerfilModel::class)->activo($perfilId);

            if ($perfil === null) {
                $this->salirPerfil();
            }
        }

        $hasta = $this->modoPadreHasta();

        return [
            'usuario'          => $usuario,
            'perfil_activo'    => $perfil?->publico(),
            'modo_padre_hasta' => $hasta === null ? null : Fechas::isoDesdeTimestamp($hasta),
        ];
    }

    private function nuevoVencimiento(): int
    {
        return Time::now()->addMinutes(self::MINUTOS_MODO_PADRE)->getTimestamp();
    }

    private function regenerar(): void
    {
        $this->session->regenerate(config(SessionConfig::class)->regenerateDestroy);
    }
}
