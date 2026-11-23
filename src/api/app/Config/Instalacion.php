<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Instalación inicial (RF-20). `token` se define en el `.env` del servidor
 * (`instalacion.token`) y se puede borrar después de instalar.
 */
class Instalacion extends BaseConfig
{
    /**
     * Token que exige `POST /instalacion` cuando `exigirToken` es verdadero.
     */
    public string $token = '';

    /**
     * `null`: se exige solo en producción. En producción un token vacío
     * deja la instalación cerrada (403), para no quedar abierta por un olvido.
     */
    public ?bool $exigirToken = null;

    public function tokenRequerido(): bool
    {
        return $this->exigirToken ?? ENVIRONMENT === 'production';
    }
}
