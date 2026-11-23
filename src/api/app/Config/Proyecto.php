<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Datos del proyecto expuestos por la API.
 */
class Proyecto extends BaseConfig
{
    /**
     * Versión que reporta `GET /api/v1/salud` (E-01).
     */
    public string $version = '0.1.0';
}
