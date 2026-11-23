<?php

declare(strict_types=1);

namespace App\Entities;

use App\Libraries\Fechas;
use CodeIgniter\Entity\Entity;

/**
 * Perfil de una hija. `publico()` y `admin()` dan las formas `PerfilPublico`
 * y `PerfilAdmin` del contrato (doc 05 §3); nunca exponen `pin_hash`.
 *
 * @property int         $id
 * @property string      $apodo
 * @property string      $avatar
 * @property string      $color
 * @property string      $nivel_ux
 * @property bool        $sonidos
 * @property string      $tema
 * @property int         $orden
 * @property bool        $activo
 * @property int         $pin_intentos
 * @property string|null $bloqueado_hasta
 */
class Perfil extends Entity
{
    protected $casts = [
        'id'           => 'integer',
        'orden'        => 'integer',
        'pin_intentos' => 'integer',
        'sonidos'      => 'boolean',
        'activo'       => 'boolean',
    ];

    protected $dates = [];

    /**
     * @return array{id: int, apodo: string, avatar: string, color: string, nivel_ux: string, sonidos: bool, tema: string, bloqueado_hasta: string|null}
     */
    public function publico(): array
    {
        return [
            'id'              => $this->id,
            'apodo'           => $this->apodo,
            'avatar'          => $this->avatar,
            'color'           => $this->color,
            'nivel_ux'        => $this->nivel_ux,
            'sonidos'         => $this->sonidos,
            'tema'            => $this->tema,
            'bloqueado_hasta' => Fechas::bloqueoVigente($this->attributes['bloqueado_hasta'] ?? null),
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function admin(): array
    {
        return $this->publico() + [
            'activo'       => $this->activo,
            'orden'        => $this->orden,
            'pin_intentos' => $this->pin_intentos,
        ];
    }
}
